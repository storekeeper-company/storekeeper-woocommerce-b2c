<?php

namespace StoreKeeper\WooCommerce\B2C\Hooks;

interface WpFilterInterface
{
    const PREFIX = 'storekeeper_';
    static function getTag(): string;
    static function getDescription(): string;
}
