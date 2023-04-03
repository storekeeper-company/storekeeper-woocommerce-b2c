<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Migrations\MigrationManager;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class Activator
{
    public function run()
    {
        $migrations = new MigrationManager();
        $migrations->migrateAll();

        $this->setVersion();
    }

    private function setVersion()
    {
        StoreKeeperOptions::set(StoreKeeperOptions::INSTALLED_VERSION, STOREKEEPER_WOOCOMMERCE_B2C_VERSION);
    }
}
