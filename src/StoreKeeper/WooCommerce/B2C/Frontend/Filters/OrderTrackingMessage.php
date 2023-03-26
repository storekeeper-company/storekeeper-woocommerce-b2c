<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Filters;

use StoreKeeper\WooCommerce\B2C\I18N;

class OrderTrackingMessage extends \StoreKeeper\WooCommerce\B2C\Hooks\AbstractWpFilter
{
    public static function getTag(): string
    {
        return self::FILTER_PREFIX.'order_tracking_message';
    }

    public static function getDescription(): string
    {
        return __("Allows to change the Track&Trace html on the order page before it's shown on the customer order page.", I18N::DOMAIN);
    }
}
