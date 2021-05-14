<?php

namespace StoreKeeper\WooCommerce\B2C\Cache;

class StoreKeeperIdCache extends AbstractCache
{
    public static function getCacheGroup()
    {
        return __CLASS__;
    }
}
