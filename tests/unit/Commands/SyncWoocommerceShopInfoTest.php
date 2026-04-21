<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceShopInfo;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class SyncWoocommerceShopInfoTest extends AbstractTest
{
    public function testRun()
    {
        $this->initApiConnection();

        $this->mockApiCallsFromDirectory('commands/shop-info', false);

        $this->runner->execute(SyncWoocommerceShopInfo::getCommandName());

        $this->assertEquals('Goor', get_option('woocommerce_store_city'));
        $this->assertSame(
            '85',
            (string) StoreKeeperOptions::get(StoreKeeperOptions::SPECIAL_COMMUNITY_INTRA_GOODS, ''),
            'ICL tax_rate_id should be fetched from backoffice and persisted'
        );
    }
}