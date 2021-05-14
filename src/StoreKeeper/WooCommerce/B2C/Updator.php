<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class Updator
{
    public function updateAction()
    {
        $this->update();
    }

    public function update(bool $forceUpdate = false)
    {
        $currentVersion = STOREKEEPER_WOOCOMMERCE_B2C_VERSION;
        $databaseVersion = StoreKeeperOptions::get(StoreKeeperOptions::INSTALLED_VERSION, '0.0.0');
        if ($forceUpdate || version_compare($currentVersion, $databaseVersion, '>')) {
            $this->handleUpdate();
        }
    }

    private function handleUpdate()
    {
        $activator = new Activator();
        $activator->run();
    }
}
