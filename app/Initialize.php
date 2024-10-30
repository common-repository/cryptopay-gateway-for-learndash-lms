<?php

declare(strict_types=1);

namespace BeycanPress\CryptoPay\LearnDash;

use BeycanPress\CryptoPay\Integrator\Helpers;

class Initialize
{
    /**
     * Constructor
     */
    public function __construct()
    {
        Helpers::registerIntegration('learndash');
        Helpers::createTransactionPage(
            esc_html__('LearnDash transactions', 'cryptopay-gateway-for-learndash-lms'),
            'learndash',
        );

        add_filter('learndash_payment_gateways', [$this, 'registerGateway']);
        add_action('learndash_settings_sections_init', [$this, 'registerSection']);
    }

    /**
     * Register gateway
     *
     * @param array<\Learndash_Payment_Gateway> $gateways
     * @return array<\Learndash_Payment_Gateway>
     */
    public function registerGateway(array $gateways): array
    {
        if (Helpers::exists()) {
            $gateways[] = new Gateways\GatewayPro();
        }

        if (Helpers::liteExists()) {
            $gateways[] = new Gateways\GatewayLite();
        }

        return $gateways;
    }

    /**
     * @return void
     */
    public function registerSection(): void
    {
        if (Helpers::exists()) {
            Sections\SectionPro::add_section_instance();
        }

        if (Helpers::liteExists()) {
            Sections\SectionLite::add_section_instance();
        }
    }
}
