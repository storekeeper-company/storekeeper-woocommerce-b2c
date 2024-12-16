<?php

namespace StoreKeeper\WooCommerce\B2C\Cache;

class LocationShippingCache extends AbstractCache
{

    public static function getCacheGroup()
    {
        return __CLASS__;
    }
}
