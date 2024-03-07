<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;

class DisconnectHandlerTest extends AbstractTest
{
    /**
     * @throws \Throwable
     */
    public function testHandleDisconnect(): void
    {
        $this->assertFalse(StoreKeeperOptions::isConnected(), 'Should not be connected');
        $this->initApiConnection();
        $this->assertTrue(StoreKeeperOptions::isConnected(), 'Should be connected');
        $this->handleDisconnectRequest();
        $this->assertFalse(StoreKeeperOptions::isConnected(), 'Should not be connected');
    }

    /**
     * @throws \Throwable
     */
    protected function handleDisconnectRequest(): void
    {
        $file = $this->getHookDataDump('hook.disconnect.json');
        $rest = $this->getRestWithToken($file);
        $this->assertEquals('disconnect', $file->getHookAction());
        $this->handleRequest($rest);
    }
}
