<?php

// From task: https://app.clickup.com/t/4pjq9m

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Regression;

use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceProducts;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\AbstractTest;

class InvalidTaxonomyTest extends AbstractTest
{
    // Datadump related constants
    const DATADUMP_DIRECTORY = 'regression/invalid-taxonomy';

    public function testRun()
    {
        // Initialize the test
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY, true);
        $this->mockApiCallsFromCommonDirectory();
        $this->mockMediaFromDirectory(self::DATADUMP_DIRECTORY.'/media');

        $this->runner->execute(SyncWooCommerceProducts::getCommandName());

        $wc_products = wc_get_products([]);
        $this->assertCount(
            1, $wc_products, 'Configurable products was imported'
        );
    }
}
