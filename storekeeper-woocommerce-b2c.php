<?php
/**
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @see              https://www.storekeeper.nl/
 * @since             0.0.1
 *
 * @wordpress-plugin
 * Plugin Name:         StoreKeeper for WooCommerce
 * Version:             0.0.1
 * Tags: woocommerce,e-commerce, woo,sales,store
 * Author:              Storekeeper
 * Author URI:          https://www.storekeeper.nl/
 * Text Domain:         storekeeper-for-woocommerce
 * Domain Path:         /i18n
 * Description:         This plugin provides sync possibilities with the StoreKeeper Backoffice.
 * Allows synchronization of the WooCommerce product catalog, customers, orders and handles payments using StoreKeeper payment platform.
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit;
}

define('STOREKEEPER_WOOCOMMERCE_B2C_VERSION', '0.0.1');
define('STOREKEEPER_WOOCOMMERCE_FILE', plugin_basename(__FILE__));

if (!defined('STOREKEEPER_WOOCOMMERCE_INTEGRATIONS')) {
    define('STOREKEEPER_WOOCOMMERCE_INTEGRATIONS', 'https://integrations.storekeeper.software');
}

if (!defined('STOREKEEPER_WOOCOMMERCE_INTEGRATIONS_USE_FLAG')) {
    define('STOREKEEPER_WOOCOMMERCE_INTEGRATIONS_USE_FLAG', true);
}

include_once __DIR__.'/autoload.php';

if (phpversion() < STOREKEEPER_WOOCOMMERCE_B2C_PHP_VERSION) {
    $txt = sprintf(
        __(
            '%s: Your PHP Version is lower then the minimum required %s, Thus the activation of this plugin will not continue.',
            \StoreKeeper\WooCommerce\B2C\I18N::DOMAIN
        ),
        STOREKEEPER_WOOCOMMERCE_B2C_NAME,
        STOREKEEPER_WOOCOMMERCE_B2C_PHP_VERSION
    );
    echo <<<HTML
<div class="notice notice-error">
<p style="color: red; text-decoration: blink;">$txt</p>
</div>
HTML;

    return;
}

register_activation_hook(__FILE__, 'activate_storekeeper_woocommerce_b2c');
register_deactivation_hook(__FILE__, 'deactivate_storekeeper_woocommerce_b2c');

/**
 * This class is being runned when you activate the plugin.
 */
function activate_storekeeper_woocommerce_b2c()
{
    $activator = new \StoreKeeper\WooCommerce\B2C\Activator();
    $activator->run();
}

/**
 * This class is being runned when you deactivate the plugin.
 */
function deactivate_storekeeper_woocommerce_b2c()
{
    $deactivator = new \StoreKeeper\WooCommerce\B2C\Deactivator();
    $deactivator->run();
}

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.0.1
 */
function storekeeper_woocommerce_b2c_run()
{
    $plugin = new \StoreKeeper\WooCommerce\B2C\Core();
    $plugin->run();
}

storekeeper_woocommerce_b2c_run();
