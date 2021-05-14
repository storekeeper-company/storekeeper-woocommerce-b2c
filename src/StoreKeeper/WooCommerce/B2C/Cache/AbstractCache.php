<?php

namespace StoreKeeper\WooCommerce\B2C\Cache;

abstract class AbstractCache
{
    private static function generateCacheGroup()
    {
        return STOREKEEPER_WOOCOMMERCE_B2C_NAME.self::getCacheGroup();
    }

    public static function getCacheGroup()
    {
        return __CLASS__;
    }

    public static function get($key)
    {
        return wp_cache_get($key, self::generateCacheGroup());
    }

    public static function set($key, $value)
    {
        return wp_cache_set($key, $value, self::generateCacheGroup());
    }

    public static function exists($key)
    {
        return false !== wp_cache_get($key, self::generateCacheGroup());
    }
}
