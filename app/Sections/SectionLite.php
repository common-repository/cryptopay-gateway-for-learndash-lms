<?php

declare(strict_types=1);

// @phpcs:disable Generic.Files.InlineHTML
// @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps

namespace BeycanPress\CryptoPay\LearnDash\Sections;

use BeycanPress\CryptoPay\LearnDash\Gateways\GatewayLite;

class SectionLite extends AbstractSection
{
    /**
     * Constructor
     */
    protected function __construct()
    {
        parent::__construct(GatewayLite::get_name(), GatewayLite::get_label());
        // LearnDash_Settings_Section::get_section_setting(SectionLite::class, 'enabled');
    }
}
