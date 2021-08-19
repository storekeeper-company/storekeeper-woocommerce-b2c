<?php

namespace StoreKeeper\WooCommerce\B2C\Cron;

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class ProcessTaskCron
{
    public function execute()
    {
        StoreKeeperOptions::set(StoreKeeperOptions::CRON_ENABLED, false);
    }
}