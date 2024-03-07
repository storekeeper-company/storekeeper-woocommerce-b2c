<?php

namespace StoreKeeper\WooCommerce\B2C\Hooks;

interface WpFilterInterface
{
    public const FILTER_PREFIX = 'storekeeper_';

    public static function getTag(): string;

    public static function getDescription(): string;
}
