<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceProducts;

class SyncWoocommerceProductsTest extends AbstractTest
{
    // Datadump related constants
    const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-products';
    const DATADUMP_SOURCE_FILE = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.72e551759ae4651bdb99611a255078af300eb8b787c2a8b9a216b800b8818b06.json';

    /**
     * Initialize the tests by following these steps:
     * 1. Initialize the API connection and the mock API calls
     * 2. Make sure there are no products imported
     * 3. Run the 'wp sk sync-woocommerce-products' command
     * 4. Run the 'wp sk process-all-tasks' command to process the tasks spawned by the import ( parent recalculation ).
     *
     * @throws \Throwable
     */
    protected function initializeTest()
    {
        // Initialize the test
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY, true);
        $this->mockMediaFromDirectory(self::DATADUMP_DIRECTORY.'/media');

        // Tests whether there are no products before import
        $wc_products = wc_get_products([]);
        $this->assertEquals(
            0,
            count($wc_products),
            'Test was not ran in an empty environment'
        );

        // Run the product import command
        $this->runner->execute(SyncWoocommerceProducts::getCommandName());
        // Process all the tasks that get spawned by the product import command
        $this->runner->execute(ProcessAllTasks::getCommandName());
    }

    /**
     * Fetch the data from the datadump source file.
     */
    protected function getReturnData(): array
    {
        // Read the original data from the data dump
        $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.self::DATADUMP_SOURCE_FILE);

        return $file->getReturn()['data'];
    }

    public function testSimpleProductImport()
    {
        $this->initializeTest();

        $original_product_data = $this->getReturnData();

        // Get the simple products from the data dump
        $original_product_data = $this->getProductsByTypeFromDataDump(
            $original_product_data,
            self::SK_TYPE_SIMPLE
        );

        // Retrieve all synchronised simple products
        $wc_simple_products = wc_get_products(['type' => self::WC_TYPE_SIMPLE]);
        $this->assertEquals(
            count($original_product_data),
            count($wc_simple_products),
            'Amount of synchronised simple products doesn\'t match source data'
        );

        foreach ($original_product_data as $product_data) {
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
            $wc_simple_product = $wc_products[0];
            $this->assertEquals(
                self::WC_TYPE_SIMPLE,
                $wc_simple_product->get_type(),
                'WooCommerce product type doesn\'t match the expected product type'
            );

            $this->assertProduct($original, $wc_simple_product);
        }
    }

    public function testConfigurableProductImport()
    {
        $this->initializeTest();

        $original_product_data = $this->getReturnData();

        // Get the configurable products from the data dump
        $original_product_data = $this->getProductsByTypeFromDataDump(
            $original_product_data,
            self::SK_TYPE_CONFIGURABLE
        );

        // Retrieve all synchronised configurable products
        $wc_configurable_products = wc_get_products(['type' => self::WC_TYPE_CONFIGURABLE]);
        $this->assertEquals(
            count($original_product_data),
            count($wc_configurable_products),
            'Amount of synchronised configurable products doesn\'t match source data'
        );

        foreach ($original_product_data as $product_data) {
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

    public function testAssignedProductImport()
    {
        $this->initializeTest();

        $original_product_data = $this->getReturnData();

        // Get the assigned products from the data dump
        $original_product_data = $this->getProductsByTypeFromDataDump(
            $original_product_data,
            self::SK_TYPE_ASSIGNED
        );

        // Create a collection with all of the variations of configurable products from WooCommerce
        $wc_assigned_products = [];
        $wc_configurable_products = wc_get_products(['type' => self::WC_TYPE_CONFIGURABLE]);
        foreach ($wc_configurable_products as $wc_configurable_product) {
            $parent_product = new \WC_Product_Variable($wc_configurable_product->get_id());
            foreach ($parent_product->get_visible_children() as $index => $visible_child_id) {
                $wc_variation_product = new \WC_Product_Variation($visible_child_id);
                $storekeeper_id = $wc_variation_product->get_meta('storekeeper_id');
                $wc_assigned_products[$storekeeper_id] = $wc_variation_product;
            }
        }

        foreach ($original_product_data as $product_data) {
            $original = new Dot($product_data);

            // Retrieve assigned product with the storekeeper_id from the source data
            $wc_assigned_product = $wc_assigned_products[$original->get('id')];
            $this->assertNotEmpty($wc_assigned_product, 'No assigned product with the given storekeeper id');

            $this->assertProduct($original, $wc_assigned_product);
        }
    }
}
