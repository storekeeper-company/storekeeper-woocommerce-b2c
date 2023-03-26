<?php

namespace StoreKeeper\WooCommerce\B2C\Hooks;

abstract class AbstractWpFilter implements WpFilterInterface
{


    static function getDescription(): string
    {
        return static::getTag();
    }
}
