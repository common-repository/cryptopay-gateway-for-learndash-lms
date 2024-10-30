<?php

declare(strict_types=1);

// @phpcs:disable Generic.Files.LineLength
// @phpcs:disable Generic.Files.InlineHTML
// @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace BeycanPress\CryptoPay\LearnDash\Gateways;

use LearnDash\Core\Models\Product;
use LearnDash\Core\Models\Transaction;

abstract class AbstractGateway extends \Learndash_Payment_Gateway
{
    /**
     * @var string
     */
    protected static string $name;

    /**
     * @var string
     */
    protected static string $title;

    /**
     * @var string
     */
    protected string $sectionClass;

    /**
     * @var array<string,mixed>
     */
    // phpcs:ignore
    protected $settings = [];

    /**
     * @var string
     */
    // phpcs:ignore
    protected $currency_code;

    /**
     * @var string
     */
    // phpcs:ignore
    protected $account_id;

    /**
     * @var \WP_User
     */
    // phpcs:ignore
    protected $user;

    /**
     * @param string $sectionClass
     */
    protected function __construct(string $sectionClass)
    {
        $this->sectionClass = $sectionClass;
        $this->currency_code = mb_strtoupper(learndash_get_currency_code());
    }

    /**
     * @param object $data
     * @return object
     */
    public function cpPaymentFinished(object $data): object
    {
        if (!$data->getStatus()) {
            return $data;
        }

        $user = new \WP_User($data->getUserId());
        $metadata = $data->getParams()->get('metadata');
        $product = Product::find(absint($metadata->post_id));
        $accessUpdates = $this->add_access_to_products([$product], $user);
        $txId = $this->record_transaction((array) $metadata, $product->get_post(), $user);

        foreach ($accessUpdates as $productId => $update) {
            if ($update) {
                $this->log_info('User enrolling into Product[' . esc_html($productId) . '] success.');
            } else {
                $this->log_info('User enrolling into Product[' . esc_html($productId) . '] failed.');
            }
        }

        $data->getOrder()->setId(intval($txId));

        return $data;
    }

    /**
     * @param object $data
     * @return array<string>
     */
    public function cpPaymentRedirectUrls(object $data): array
    {
        $productId = $data->getParams()->get('metadata.post_id');
        $product = Product::find(absint($productId));
        return [
            'success' => get_permalink($product->get_post()),
            'failed' => $this->get_url_fail([$product]),
        ];
    }

    /**
     * @param Product $product
     * @return void
     */
    abstract protected function start(Product $product): void;

    /**
     * @return array<string>
     */
    abstract protected function get_deps(): array;

    /**
     * @return bool
     */
    abstract protected function is_test_mode(): bool;

    /**
     * @return string
     */
    public static function get_name(): string
    {
        return static::$name;
    }

    /**
     * @return string
     */
    public static function get_label(): string
    {
        return static::$title;
    }

    /**
     * @return void
     */
    public function add_extra_hooks(): void
    {
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->settings = \LearnDash_Settings_Section::get_section_settings_all(
            $this->sectionClass
        );
    }

    /**
     * @return bool
     */
    public function is_ready(): bool
    {
        return 'yes' === ($this->settings['enabled'] ?? '');
    }

    /**
     * It's a ajax action.
     * @return void
     */
    public function setup_payment(): void
    {
        // Nonce verification in LearnDash side
        if (!$productId = isset($_POST['productId']) ? absint($_POST['productId']) : 0) {
            return;
        }

        $product = Product::find($productId);

        if (!$product) {
            wp_send_json_error([
                'msg' => esc_html__('Product not found.', 'cryptopay-gateway-for-learndash-lms')
            ]);
        }

        $this->user = new \WP_User(get_current_user_id());
        $productPricing = $this->user ? $product->get_pricing($this->user) : $product->get_pricing();

        $coursePrice = apply_filters(
            'learndash_get_price_by_coupon',
            $productPricing->price,
            $product->get_id(),
            $this->user?->ID ?? 0
        );

        $paymentIntentData = null;
        $subscriptionData  = null;

        try {
            if ($product->is_price_type_paynow()) {
                $paymentIntentData = $this->get_order_data($coursePrice, $product);
            } elseif ($product->is_price_type_subscribe()) {
                $subscriptionData = $this->get_subscription_data($coursePrice, $productPricing, $product);
            }
        } catch (\Throwable $th) {
            wp_send_json_error([
                'msg' => $th->getMessage()
            ]);
        }

        wp_send_json_success($paymentIntentData ?? $subscriptionData);
    }

    /**
     * @param float $amount
     * @param Product $product
     * @return array<mixed>
     */
    private function get_order_data(float $amount, Product $product): array
    {
        $transactionMetaDto = \Learndash_Transaction_Meta_DTO::create(
            [
                Transaction::$meta_key_gateway_name => self::get_name(),
                Transaction::$meta_key_price_type   => LEARNDASH_PRICE_TYPE_PAYNOW,
                Transaction::$meta_key_pricing_info => \Learndash_Pricing_DTO::create(
                    [
                        'currency' => $this->currency_code,
                        'price'    => number_format($amount / 100, 2, '.', ''),
                    ]
                ),
            ]
        );

        $item = [
            'amount' => $amount,
            'currency' => $this->currency_code
        ];

        $metadata = array_merge(
            [
                'is_learndash'      => true,
                'learndash_version' => LEARNDASH_VERSION,
                'post_id'           => $product->get_id(),
            ],
            array_map(
                function ($value) {
                    return is_array($value) ? wp_json_encode($value) : $value;
                },
                $transactionMetaDto->to_array()
            )
        );

        return [
            'item'         => $item,
            'metadata'     => $metadata
        ];
    }

    /**
     *
     * @param float                 $amount  Amount.
     * @param \Learndash_Pricing_DTO $pricing Pricing DTO.
     * @param Product               $product Product.
     *
     * @throws \Exception Exception.
     *
     * @return array<string,mixed>
     */
    private function get_subscription_data(float $amount, \Learndash_Pricing_DTO $pricing, Product $product): array
    {
        if (empty($pricing->duration_length)) {
            throw new \Exception(esc_html__('The Billing Cycle Interval value must be set.', 'cryptopay-gateway-for-learndash-lms'));
        } elseif (0 === $pricing->duration_value) {
            throw new \Exception(esc_html__('The minimum Billing Cycle value is 1.', 'cryptopay-gateway-for-learndash-lms'));
        }

        $trialDurationInDays = $this->map_trial_duration_in_days(
            $pricing->trial_duration_value,
            $pricing->trial_duration_length
        );

        $hasTrial         = $trialDurationInDays > 0;
        $courseTrialPrice = $hasTrial ? $pricing->trial_price : 0.;

        $transactionMetaDto = \Learndash_Transaction_Meta_DTO::create(
            [
                Transaction::$meta_key_gateway_name   => self::get_name(),
                Transaction::$meta_key_price_type     => LEARNDASH_PRICE_TYPE_SUBSCRIBE,
                Transaction::$meta_key_pricing_info   => $pricing,
                Transaction::$meta_key_has_trial      => $hasTrial,
                Transaction::$meta_key_has_free_trial => $hasTrial && 0. === $courseTrialPrice,
            ]
        );

        $item = [
            'amount'    => $amount,
            'currency' => $this->currency_code,
        ];

        $metadata = array_merge(
            [
                'is_learndash'      => true,
                'learndash_version' => LEARNDASH_VERSION,
                'post_id'           => $product->get_id(),
                'trial_period_days' => $trialDurationInDays > 0 ? $trialDurationInDays : null,
            ],
            array_map(
                function ($value) {
                    return is_array($value) ? wp_json_encode($value) : $value;
                },
                $transactionMetaDto->to_array()
            )
        );

        return [
            'item'         => $item,
            'metadata'     => $metadata,
        ];
    }

    /**
     * @param int $durationValue
     * @param string $durationLength
     * @return int
     */
    private function map_trial_duration_in_days(int $durationValue, string $durationLength): int
    {
        if (0 === $durationValue || empty($durationLength)) {
            return 0;
        }

        $durationNumberInDaysByLength = [
            'D' => 1,
            'W' => 7,
            'M' => 30,
            'Y' => 365,
        ];

        return $durationValue * $durationNumberInDaysByLength[$durationLength];
    }

    /**
     * @param array<mixed> $params
     * @param \WP_Post $post
     * @return string
     */
    public function map_payment_button_markup(array $params, \WP_Post $post): string
    {
        $buttonLabel = $this->map_payment_button_label(
            self::get_label(),
            $post
        );

        if (!is_user_logged_in()) {
            $loginUrl = learndash_get_login_url();
            return '<a href="' . esc_url($loginUrl) . '" class="ldlms-cp-btn">' . esc_attr($buttonLabel) . '</a>';
        }

        $product = Product::find(absint($post->ID));
        $this->start($product);

        $jsonData = [
            'productId' => $product->get_id()
        ];

        $button = '<div class="' . esc_attr($this->get_form_class_name()) . '"><button class="ldlms-cp-btn" type="button" data-name="' . esc_attr(self::get_name()) . '" data-json="\'' . esc_attr(wp_json_encode($jsonData)) . '\'">' . esc_attr($buttonLabel) . '</button></div>';

        return $button;
    }

    /**
     * @param mixed $entity
     * @param Product $product
     * @return string
     */
    protected function map_transaction_meta(mixed $entity, Product $product): \Learndash_Transaction_Meta_DTO
    {
        $is_subscription = 'subscription' === $entity->mode;

        $meta = array_merge(
            $entity->metadata ? $entity->metadata->toArray() : [],
            [
                Transaction::$meta_key_gateway_transaction => \Learndash_Transaction_Gateway_Transaction_DTO::create(
                    [
                        'id'    => $is_subscription ? $entity->subscription : $entity->payment_intent,
                        'event' => $entity,
                    ]
                ),
            ]
        );

        $meta = $this->process_legacy_meta(
            $entity,
            $meta,
            $is_subscription,
            $entity->metadata->learndash_version ?? '',
            $product
        );

        // It was encoded to allow arrays in the metadata.
        if (is_string($meta[Transaction::$meta_key_pricing_info])) {
            $meta[Transaction::$meta_key_pricing_info] = json_decode(
                $meta[Transaction::$meta_key_pricing_info],
                true
            );
        }

        return \Learndash_Transaction_Meta_DTO::create($meta);
    }

    /**
     * @return void
     */
    public function enqueue_scripts(): void
    {
        $deps = array_merge(['jquery'], $this->get_deps());
        wp_enqueue_style('ldlms-cp-style', LDLMS_CRYPTOPAY_URL . 'assets/css/main.css', [], LDLMS_CRYPTOPAY_VERSION);
        wp_enqueue_script('ldlms-cp-script', LDLMS_CRYPTOPAY_URL . 'assets/js/main.js', $deps, LDLMS_CRYPTOPAY_VERSION, true);

        $ajaxUrl = admin_url('admin-ajax.php');
        $action = $this->get_ajax_action_name_setup();
        wp_localize_script('ldlms-cp-script', 'LDLMSCP', [
            'action' => $action,
            'ajaxUrl' => $ajaxUrl,
            'lang' => [
                'waiting' => esc_html__('Please wait...', 'cryptopay-gateway-for-learndash-lms'),
            ]
        ]);
    }

    /**
     * @return void
     */
    public function process_webhook(): void
    {
    }
}
