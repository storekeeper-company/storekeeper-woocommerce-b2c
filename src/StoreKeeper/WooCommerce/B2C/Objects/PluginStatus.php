<?php

namespace StoreKeeper\WooCommerce\B2C\Objects;

class PluginStatus
{
    public const WOO_VARIATION_SWATCHES = 'woo-variation-swatches/woo-variation-swatches.php';
    public const SK_SWATCHES = 'storekeeper-woocommerce-swatches/index.php';
    public const PORTO_FUNCTIONALITY = 'porto-functionality/porto-functionality.php';
    public const YOAST_SEO = 'wordpress-seo/wp-seo.php';
    public const RANK_MATH_SEO = 'seo-by-rank-math/rank-math.php';
    public const PRODUCT_X = 'product-blocks/product-blocks.php';
    public const PRODUCT_X_COMPATIBLE_VERSION = '3.1.15';
    private static $plugins;

    private static function getPlugins()
    {
        if (!isset(self::$plugins)) {
            // This fetches the installed AND active plugins
            self::$plugins = apply_filters('active_plugins', get_option('active_plugins'));
        }

        return self::$plugins;
    }

    public static function isEnabled($plugin_path): bool
    {
        return in_array($plugin_path, self::getPlugins(), false);
    }

    public static function isYoastSeoEnabled(): bool
    {
        return self::isEnabled(self::YOAST_SEO);
    }

    public static function isRankMathSeoEnabled(): bool
    {
        return self::isEnabled(self::RANK_MATH_SEO);
    }

    public static function isWoocommerceVariationSwatchesEnabled(): bool
    {
        return self::isEnabled(self::WOO_VARIATION_SWATCHES);
    }

    public static function isStoreKeeperSwatchesEnabled(): bool
    {
        return self::isEnabled(self::SK_SWATCHES);
    }

    public static function isProductXEnabled(): bool
    {
        return self::isEnabled(self::PRODUCT_X);
    }

    public static function isPortoFunctionalityEnabled(): bool
    {
        return self::isEnabled(self::PORTO_FUNCTIONALITY);
    }
}
