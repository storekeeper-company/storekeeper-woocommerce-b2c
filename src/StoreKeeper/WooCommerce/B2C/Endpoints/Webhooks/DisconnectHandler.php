<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks;

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class DisconnectHandler
{
    public function run(): bool
    {
        return StoreKeeperOptions::disconnect();
    }
}
