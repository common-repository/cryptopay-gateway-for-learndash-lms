<?php

declare(strict_types=1);

// @phpcs:disable Generic.Files.InlineHTML
// @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace BeycanPress\CryptoPay\LearnDash\Sections;

abstract class AbstractSection extends \LearnDash_Settings_Section
{
    /**
     * @var string
     */
    // phpcs:ignore
    public $settings_page_id;

    /**
     * @var string
     */
    // phpcs:ignore
    public $setting_option_key;

    /**
     * @var string
     */
    // phpcs:ignore
    public $setting_field_prefix;

    /**
     * @var string
     */
    // phpcs:ignore
    public $settings_section_key;

    /**
     * @var string
     */
    // phpcs:ignore
    public $settings_section_label;

    /**
     * @var string
     */
    // phpcs:ignore
    public $settings_parent_section_key;

    /**
     * @var string
     */
    // phpcs:ignore
    public $settings_section_listing_label;

    /**
     * @var array<int|string,mixed>
     */
    // phpcs:ignore
    public $setting_option_values = array();

    /**
     * @var array<array<string,mixed>>
     */
    // phpcs:ignore
    public $setting_option_fields = array();

    /**
     * @param string $gatewayName
     * @param string $gatewayTitle
     */
    protected function __construct(string $gatewayName, string $gatewayTitle)
    {
        $this->settings_page_id = 'learndash_lms_payments';
        $this->settings_parent_section_key = 'settings_payments_list';

        $this->setting_option_key = 'learndash_' . $gatewayName . '_settings';
        $this->setting_field_prefix = 'learndash_' . $gatewayName . '_settings';
        $this->settings_section_key = 'settings_' . $gatewayName;

        $this->settings_section_listing_label = $gatewayTitle;
        /* translators: %s: Gateway Title */
        $this->settings_section_label = sprintf(
            esc_html__('%s Settings', 'cryptopay-gateway-for-learndash-lms'),
            $gatewayTitle
        );

        parent::__construct();
    }

    /**
     * @return void
     */
    public function load_settings_values(): void
    {
        parent::load_settings_values();

        $this->setting_option_values['theme'] = $this->setting_option_values['theme'] ?? 'light';
    }

    /**
     * @return void
     */
    public function load_settings_fields(): void
    {
        $this->setting_option_fields = [
            'enabled' => [
                'name'    => 'enabled',
                'type'    => 'checkbox-switch',
                'label'   => esc_html__('Active', 'cryptopay-gateway-for-learndash-lms'),
                'value'   => $this->setting_option_values['enabled'] ?? '',
                'options' => [
                    'yes' => '',
                    ''    => '',
                ],
            ],
            'theme' => [
                'name'      => 'theme',
                'label'     => esc_html__('Theme', 'cryptopay-gateway-for-learndash-lms'),
                'type'      => 'select',
                'options'   => [
                    'light' => esc_html__('Light', 'cryptopay-gateway-for-learndash-lms'),
                    'dark'  => esc_html__('Dark', 'cryptopay-gateway-for-learndash-lms'),
                ],
                'default'   => 'light',
                'value'     => $this->setting_option_values['theme'] ?? 'light',
            ],
        ];

        $this->setting_option_fields = apply_filters(
            'learndash_settings_fields',
            $this->setting_option_fields,
            $this->settings_section_key
        );

        parent::load_settings_fields();
    }

    /**
     * Filter the section saved values.
     *
     * @param array<array<mixed>> $value An array of setting fields values.
     * @param array<array<mixed>> $oldValue An array of setting fields old values.
     * @param string $settingsSectionKey Settings section key.
     * @param string $settingsScreenId Settings screen ID.
     *
     * @return array<array<mixed>>
     * @since 4.0.0
     */
    // phpcs:ignore
    public function filter_section_save_fields($value, $oldValue, $settingsSectionKey, $settingsScreenId): array
    {
        if ($settingsSectionKey !== $this->settings_section_key) {
            return $value;
        }

        if (!isset($value['enabled'])) {
            $value['enabled'] = '';
        }

        if (isset($_POST['learndash_settings_payments_list_nonce'])) {
            if (!is_array($oldValue)) {
                $oldValue = [];
            }

            foreach ($value as $valueIdx => $valueVal) {
                $oldValue[$valueIdx] = $valueVal;
            }

            $value = $oldValue;
        }

        return $value;
    }
}
