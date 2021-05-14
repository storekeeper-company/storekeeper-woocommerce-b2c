<?php

namespace StoreKeeper\WooCommerce\B2C\Objects;

class PluginStatus
{
    const WOO_VARIATION_SWATCHES = 'woo-variation-swatches/woo-variation-swatches.php';
    const SK_SWATCHES = 'storekeeper-woocommerce-swatches/index.php';
    const PORTO_FUNCTIONALITY = 'porto-functionality/porto-functionality.php';

    private static $plugins;

    private static function getPlugins()
    {
        if (!isset(self::$plugins)) {
            // This fetches the installed AND active plugins
            self::$plugins = get_option('active_plugins');
        }

        return self::$plugins;
    }

    public static function isEnabled($plugin_path)
    {
        return in_array($plugin_path, self::getPlugins(), false);
    }

    public static function isWoocommerceVariationSwatchesEnabled()
    {
        return self::isEnabled(self::WOO_VARIATION_SWATCHES);
    }

    public static function isStoreKeeperSwatchesEnabled()
    {
        return self::isEnabled(self::SK_SWATCHES);
    }

    public static function isPortoFunctionalityEnabled()
    {
        return self::isEnabled(self::PORTO_FUNCTIONALITY);
    }
}
