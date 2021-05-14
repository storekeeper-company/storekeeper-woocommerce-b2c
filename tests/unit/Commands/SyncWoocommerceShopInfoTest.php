<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceShopInfo;

class SyncWoocommerceShopInfoTest extends AbstractTest
{
    public function testRun()
    {
        $this->initApiConnection();

        $this->mockApiCallsFromDirectory('commands/shop-info', false);

        $this->runner->execute(SyncWoocommerceShopInfo::getCommandName());

        $this->assertEquals('Goor', get_option('woocommerce_store_city'));
    }
}
