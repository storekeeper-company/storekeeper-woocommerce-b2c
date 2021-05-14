<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;

class InitHandlerTest extends AbstractTest
{
    /**
     * test if init works.
     *
     * @throws \Throwable
     */
    public function testHandleOk()
    {
        $this->assertFalse(StoreKeeperOptions::isConnected(), 'not connected');
        $this->initApiConnection();
        $this->assertTrue(StoreKeeperOptions::isConnected(), 'is connected');
    }
}
