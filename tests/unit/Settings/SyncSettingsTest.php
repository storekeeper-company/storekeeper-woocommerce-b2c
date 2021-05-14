<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Settings;

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\UnitTest\AbstractTest;

class SyncSettingsTest extends AbstractTest
{
    public function testInitialMode()
    {
        $this->assertEquals(
            StoreKeeperOptions::SYNC_MODE_FULL_SYNC,
            StoreKeeperOptions::getSyncMode(),
            'Initial sync mode is incorrect.'
        );
    }

    public function testSameThanInitialMode()
    {
        $this->setFullSync();

        $this->assertEquals(
            StoreKeeperOptions::SYNC_MODE_FULL_SYNC,
            StoreKeeperOptions::getSyncMode(),
            'Sync mode is not full sync.'
        );
    }

    public function testDifferentThanInitialMode()
    {
        $this->setOrderOnly();

        $this->assertEquals(
            StoreKeeperOptions::SYNC_MODE_ORDER_ONLY,
            StoreKeeperOptions::getSyncMode(),
            'Sync mode is not full sync.'
        );
    }

    public function testModeChange()
    {
        $this->setOrderOnly();

        $this->setFullSync();

        $this->assertEquals(
            StoreKeeperOptions::SYNC_MODE_FULL_SYNC,
            StoreKeeperOptions::getSyncMode(),
            'Sync mode is not full sync.'
        );
    }

    private function setOrderOnly()
    {
        StoreKeeperOptions::set(
            StoreKeeperOptions::SYNC_MODE,
            StoreKeeperOptions::SYNC_MODE_ORDER_ONLY
        );
    }

    private function setFullSync()
    {
        StoreKeeperOptions::set(
            StoreKeeperOptions::SYNC_MODE,
            StoreKeeperOptions::SYNC_MODE_FULL_SYNC
        );
    }
}
