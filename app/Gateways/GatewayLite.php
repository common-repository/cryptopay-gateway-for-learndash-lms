<?php

declare(strict_types=1);

// @phpcs:disable Generic.Files.LineLength
// @phpcs:disable Generic.Files.InlineHTML
// @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace BeycanPress\CryptoPay\LearnDash\Gateways;

use LearnDash\Core\Models\Product;
use BeycanPress\CryptoPayLite\Payment;
use BeycanPress\CryptoPayLite\Helpers;
use BeycanPress\CryptoPayLite\PluginHero\Hook;
use BeycanPress\CryptoPay\LearnDash\Sections\SectionLite;

class GatewayLite extends AbstractGateway
{
    public static string $name = 'cryptopay_lite';

    public static string $title = 'CryptoPay Lite';

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct(SectionLite::class);
        Hook::addFilter('before_payment_finished_learndash', [$this, 'cpPaymentFinished']);
        Hook::addFilter('payment_redirect_urls_learndash', [$this, 'cpPaymentRedirectUrls']);
    }

    /**
     * @param Product $product
     * @return void
     */
    public function start(Product $product): void
    {
        if (!$this->is_ready()) {
            return;
        }

        Hook::addFilter('theme', function (array $theme) {
            $theme['mode'] = isset($this->settings['theme']) ? $this->settings['theme'] : 'light';
            return $theme;
        });

        $cp = (new Payment('learndash'))->modal();

        add_action('wp_footer', function () use ($cp): void {
            Helpers::ksesEcho($cp);
        });
    }

    /**
     * @return array<string>
     */
    public function get_deps(): array
    {
        return [Helpers::getProp('mainJsKey', '')];
    }

    /**
     * @return bool
     */
    protected function is_test_mode(): bool
    {
        return Helpers::getTestnetStatus();
    }
}
