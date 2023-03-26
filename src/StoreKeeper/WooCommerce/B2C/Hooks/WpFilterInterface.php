<?php

namespace StoreKeeper\WooCommerce\B2C\Hooks;

interface WpFilterInterface
{
    const FILTER_PREFIX = 'storekeeper_';
    static function getTag(): string;
    static function getDescription(): string;
}
