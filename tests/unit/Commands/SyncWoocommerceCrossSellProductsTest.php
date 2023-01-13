<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceCrossSellProducts;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceProducts;

class SyncWoocommerceCrossSellProductsTest extends AbstractTest
{
    // Datadump related constants
    const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-cross-sell-products';

    public function testInit()
    {
        $amount_tested = 0;

        // Initialize the test
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY, true);
        $this->mockApiCallsFromCommonDirectory();
        $this->mockMediaFromDirectory(self::DATADUMP_DIRECTORY.'/media');

        // Test whether there are no products before import
        $wc_all_products = wc_get_products([]);
        $this->assertEquals(
            0,
            count($wc_all_products),
            'Test was not ran in an empty environment'
        );

        // Run the product import command. This is needed so there are products to attach cross-sell product to
        $this->runner->execute(SyncWoocommerceProducts::getCommandName());
        // Run the cross sell product import command
        $this->runner->execute(SyncWoocommerceCrossSellProducts::getCommandName());

        $cross_sell_files = $this->getCrossSellDataDumpFiles();
        foreach ($cross_sell_files as $file) {
            // Retrieve the source data
            $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.$file);
            $return_data = $file->getReturn();
            // If the source data is not empty, check the validity of the wordpress data against it
            if (!empty($return_data)) {
                foreach ($return_data as $expected_cross_sell_id) {
                    ++$amount_tested;

                    // Retrieve the storekeeper id of the 'parent' product from the datadump
                    $this->assertEquals(
                        1,
                        count($file->getData()['params']),
                        'More than one id passed in the params'
                    );
                    $parent_storekeeper_id = $file->getData()['params'][0];

                    // Retrieve product(s) with the storekeeper_id
                    $wc_products = wc_get_products(
                        [
                            'post_type' => 'product',
                            'meta_key' => 'storekeeper_id',
                            'meta_value' => $parent_storekeeper_id,
                        ]
                    );
                    $this->assertEquals(
                        1,
                        count($wc_products),
                        'More than one product found with the provided storekeeper_id'
                    );

                    // Get the product with the storekeeper_id
                    $wc_product = $wc_products[0];

                    // Check whether the cross_sell id is set on the woocommerce product
                    $sk_cross_sell_ids = [];
                    $wc_cross_sell_ids = $wc_product->get_cross_sell_ids();
                    foreach ($wc_cross_sell_ids as $wc_cross_sell_id) {
                        $wc_cross_sell_product = new \WC_Product($wc_cross_sell_id);
                        $storekeeper_id = get_post_meta($wc_cross_sell_product->get_id(), 'storekeeper_id');
                        array_push($sk_cross_sell_ids, $storekeeper_id[0]);
                    }
                    $this->assertContains(
                        $expected_cross_sell_id,
                        $sk_cross_sell_ids,
                        'No WooCommerce product found with the expected cross sell id from data dump'
                    );
                }
            }
        }

        $this->assertGreaterThan(
            0,
            $amount_tested,
            'No upsell product synchronisation was actually tested'
        );
    }

    protected function getCrossSellDataDumpFiles()
    {
        return array_filter(
            scandir($this->getDataDir().self::DATADUMP_DIRECTORY),
            function ($k) {
                return false != strpos(strtolower($k), 'getcrosssellshopproductids');
            }
        );
    }
}
