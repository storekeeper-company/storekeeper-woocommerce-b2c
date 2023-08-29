<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceProducts;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceUpsellProducts;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

class SyncWoocommerceUpsellProductsTest extends AbstractTest
{
    const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-upsell-products';

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

        // Run the product import command. This is needed so there are products to attach upsell product to
        $this->runner->execute(SyncWoocommerceProducts::getCommandName());
        // Run the upsell product import command
        $this->runner->execute(SyncWoocommerceUpsellProducts::getCommandName());

        $upsell_files = $this->getUpsellDataDumpFiles();
        foreach ($upsell_files as $file) {
            // Retrieve the source data
            $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.$file.'.json');
            $return_data = $file->getReturn();
            // If the source data is not empty, check the validity of the wordpress data against it
            if (!empty($return_data)) {
                foreach ($return_data as $expected_upsell_id) {
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

                    // Check whether the up id is set on the woocommerce product
                    $sk_upsell_ids = [];
                    $wc_upsell_ids = $wc_product->get_upsell_ids();
                    foreach ($wc_upsell_ids as $wc_upsell_id) {
                        $wc_upsell_product = new \WC_Product($wc_upsell_id);
                        $storekeeper_id = get_post_meta($wc_upsell_product->get_id(), 'storekeeper_id');
                        array_push($sk_upsell_ids, (int) $storekeeper_id[0]);
                    }
                    $this->assertContains(
                        $expected_upsell_id,
                        $sk_upsell_ids,
                        'No WooCommerce product found with the expected upsell id from data dump'
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

    protected function getUpsellDataDumpFiles()
    {
        return array_filter(
            StoreKeeperApi::$mockAdapter->getUsedReturns(),
            function ($k) {
                return false != strpos(strtolower($k), 'getupsellshopproductids');
            }
        );
    }
}
