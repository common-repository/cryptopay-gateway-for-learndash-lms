<?php

declare(strict_types=1);

defined('ABSPATH') || exit;

// @phpcs:disable PSR1.Files.SideEffects
// @phpcs:disable PSR12.Files.FileHeader
// @phpcs:disable Generic.Files.InlineHTML
// @phpcs:disable Generic.Files.LineLength

/**
 * Plugin Name: CryptoPay Gateway for LearnDash LMS
 * Version:     1.0.0
 * Plugin URI:  https://beycanpress.com/cryptopay/
 * Description: Adds Cryptocurrency payment gateway (CryptoPay) for LearnDash LMS.
 * Author:      BeycanPress LLC
 * Author URI:  https://beycanpress.com
 * License:     GPLv3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: cryptopay-gateway-for-learndash-lms
 * Tags: Bitcoin, Ethereum, Cryptocurrency, Payments, LearnDash LMS
 * Requires at least: 5.0
 * Tested up to: 6.6
 * Requires PHP: 8.1
*/

// Autoload
require_once __DIR__ . '/vendor/autoload.php';

use BeycanPress\CryptoPay\Integrator\Helpers;

define('LDLMS_CRYPTOPAY_FILE', __FILE__);
define('LDLMS_CRYPTOPAY_VERSION', '1.0.0');
define('LDLMS_CRYPTOPAY_URL', plugin_dir_url(__FILE__));
define('LDLMS_CRYPTOPAY_DIR', plugin_dir_path(__FILE__));

/**
 * @return void
 */
function learndash_cryptopay_addModels(): void
{
    Helpers::registerModel(BeycanPress\CryptoPay\LearnDash\Models\TransactionsPro::class);
    Helpers::registerLiteModel(BeycanPress\CryptoPay\LearnDash\Models\TransactionsLite::class);
}

learndash_cryptopay_addModels();

add_action('plugins_loaded', function (): void {

    learndash_cryptopay_addModels();

    load_plugin_textdomain('cryptopay-gateway-for-learndash-lms', false, basename(__DIR__) . '/languages');

    if (!defined('LEARNDASH_VERSION')) {
        Helpers::requirePluginMessage('LearnDash LMS', 'https://www.learndash.com/', false);
    } elseif (Helpers::bothExists()) {
        new BeycanPress\CryptoPay\LearnDash\Initialize();
    } else {
        Helpers::requireCryptoPayMessage('LearnDash LMS');
    }
});
