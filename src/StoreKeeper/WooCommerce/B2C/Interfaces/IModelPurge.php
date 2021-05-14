<?php

namespace StoreKeeper\WooCommerce\B2C\Interfaces;

interface IModelPurge extends IModel
{
    public static function purge(): int;
}
