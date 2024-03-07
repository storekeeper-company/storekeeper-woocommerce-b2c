<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Regression;

use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceFullSync;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\AbstractTest;

class OutOfSyncAttributeOptionsTest extends AbstractTest
{
    public const DATADUMP_DIRECTORY = 'regression/out-of-sync-attribute-options';
    public const HOOK_FILE = '20200527_050939.hook.events.success.5ecdf6137a671.json';

    public function testOutOfSyncOptions()
    {
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY.'/full-sync', true);
        $this->mockApiCallsFromCommonDirectory();
        $this->mockMediaFromDirectory(self::DATADUMP_DIRECTORY.'/full-sync/media');

        $this->runner->execute(SyncWoocommerceFullSync::getCommandName());
        $this->runner->execute(ProcessAllTasks::getCommandName());

        /** @var $product \WC_Product_Simple */
        $product = wc_get_products(
            [
                'limit' => 1,
                'type' => 'simple',
            ]
        )[0];

        $this->assertNotNull(
            $product,
            'No product imported'
        );

        // Get initial Kleur varaible
        $preAttribute = $product->get_attribute('kleur');

        // trigger product update hook
        $targetDir = self::DATADUMP_DIRECTORY.'/'.self::HOOK_FILE;
        $file = $this->getHookDataDump($targetDir);
        $rest = $this->getRestWithToken($file);
        $response = $this->handleRequest($rest);
        $data = $response->get_data();
        $this->assertTrue($data['success'], 'request failed');

        // process the tasks
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY.'/simple-product-update', true);
        $this->mockMediaFromDirectory(self::DATADUMP_DIRECTORY.'/simple-product-update/media');
        $this->runner->execute(ProcessAllTasks::getCommandName());

        // Get after kleur varaible
        $product = wc_get_product($product);
        $postAttribute = $product->get_attribute('kleur');

        // Kleur should be different
        $this->assertNotEquals(
            $preAttribute,
            $postAttribute,
            'Attribute option did not update'
        );
    }
}
