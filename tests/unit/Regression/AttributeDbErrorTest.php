<?php

// From task: https://app.clickup.com/t/4jhkxk

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Regression;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceProducts;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\AbstractTest;

class AttributeDbErrorTest extends AbstractTest
{
    // Datadump related constants
    public const DATADUMP_DIRECTORY = 'regression/attribute-db-error';
    public const DATADUMP_SOURCE_FILE = '20200416_092835.moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.success.5e982542c4622.json';

    public function testRun()
    {
        // Initialize the test
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY, true);
        $this->mockApiCallsFromCommonDirectory();
        $this->mockMediaFromDirectory(self::DATADUMP_DIRECTORY.'/media');

        // Make sure the environment was clean
        $products = get_posts(['post_type' => 'product']);
        $this->assertCount(0, $products, 'Product was not ran in an empty environment.');

        $this->runner->execute(SyncWoocommerceProducts::getCommandName());

        $products_data = $this->getReturnData();
        $products_dump = $this->getProductsByTypeFromDataDump($products_data, self::SK_TYPE_CONFIGURABLE);

        // Get the amount of configurable products in the datadump
        $dump_amount = count($products_dump);

        // Make sure the right amount of products were made
        $products = wc_get_products(['type' => self::WC_TYPE_CONFIGURABLE]);
        $this->assertCount($dump_amount, $products, 'Not all products were created.');

        // Make sure 10 requests were made
        $used_keys = StoreKeeperApi::$mockAdapter->getUsedReturns();
        $this->assertNotEmpty($used_keys, 'Calls were made');

        // Make sure all configurable product data is correct
        foreach ($products_dump as $product_data) {
            $original = new Dot($product_data);

            // Retrieve product(s) with the storekeeper_id from the source data
            $wc_products = wc_get_products(
                [
                    'post_type' => 'product',
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $original->get('id'),
                ]
            );

            $this->assertEquals(
                1,
                count($wc_products),
                'More then one product found with the provided storekeeper_id'
            );

            // Get the simple product with the storekeeper_id
            $wc_configurable_product = $wc_products[0];
            $this->assertEquals(
                self::WC_TYPE_CONFIGURABLE,
                $wc_configurable_product->get_type(),
                'WooCommerce product type doesn\'t match the expected product type'
            );

            $this->assertProduct($original, $wc_configurable_product);
        }
    }

    /**
     * Fetch the data from the datadump source file.
     */
    private function getReturnData(): array
    {
        // Read the original data from the data dump
        $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.self::DATADUMP_SOURCE_FILE);

        return $file->getReturn()['data'];
    }
}
