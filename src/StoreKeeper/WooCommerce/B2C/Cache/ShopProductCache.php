<?php

namespace StoreKeeper\WooCommerce\B2C\Cache;

class ShopProductCache extends AbstractCache
{
    public static function getCacheGroup()
    {
        return __CLASS__;
    }
}
