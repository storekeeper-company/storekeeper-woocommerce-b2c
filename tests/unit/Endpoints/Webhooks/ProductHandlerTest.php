<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\UnitTest\AbstractProductTest;
use WC_Helper_Product;

class ProductHandlerTest extends AbstractProductTest
{
    const MEDIA_DATADUMP_DIRECTORY = 'events/products/media';

    const CREATE_DATADUMP_DIRECTORY = 'events/products/createProduct';
    const CREATE_DATADUMP_HOOK = 'events/hook.events.createProduct.json';
    const CREATE_DATADUMP_PRODUCT = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.bd9e4c8829238df3a0f78246f8df8690ca1c8cdd31bcb1b44a19132c10feee96.json';

    const UPDATE_DATADUMP_DIRECTORY = 'events/products/updateProduct';
    const UPDATE_DATADUMP_HOOK = 'events/hook.events.updateProduct.json';
    const UPDATE_DATADUMP_PRODUCT = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.bd9e4c8829238df3a0f78246f8df8690ca1c8cdd31bcb1b44a19132c10feee96.json';

    const DEACTIVATE_DATADUMP_DIRECTORY = 'events/products/deactivateProduct';
    const DEACTIVATE_DATADUMP_HOOK = 'events/hook.events.deactivateProduct.json';
    const DEACTIVATE_DATADUMP_PRODUCT = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.bd9e4c8829238df3a0f78246f8df8690ca1c8cdd31bcb1b44a19132c10feee96.json';

    const ACTIVATE_DATADUMP_DIRECTORY = 'events/products/activateProduct';
    const ACTIVATE_DATADUMP_HOOK = 'events/hook.events.activateProduct.json';
    const ACTIVATE_DATADUMP_PRODUCT = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.bd9e4c8829238df3a0f78246f8df8690ca1c8cdd31bcb1b44a19132c10feee96.json';

    const POST_STATUS_TRASHED = 'trash';

    public function testCreateProduct()
    {
        // Handle the product creation hook event
        $original_options = $this->handle_hook_request(
            self::CREATE_DATADUMP_DIRECTORY,
            self::CREATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
        );

        // Retrieve the product from wordpress using the storekeeper id
        $wc_products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $original_options['id'],
            ]
        );
        $this->assertCount(
            1,
            $wc_products,
            'Actual size of the retrieved product collection is not equal to one'
        );

        $wc_created_product = $wc_products[0];

        // Get the updated data dump as a dotnotated collection
        $create_file = $this->getDataDump(self::CREATE_DATADUMP_DIRECTORY.'/'.self::CREATE_DATADUMP_PRODUCT);
        $created_product_data = $create_file->getReturn()['data'];
        $created_product_data = new Dot($created_product_data[0]);

        // Compare the values of the updated data dump against the updated wordpress product
        $this->assertProduct($created_product_data, $wc_created_product);

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testUpdateProduct()
    {
        // Handle the product creation hook event so there is a product to update
        $creation_options = $this->handle_hook_request(
            self::CREATE_DATADUMP_DIRECTORY,
            self::CREATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
        );
        // Fetch the product that was created by the creation hook
        $wc_products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $creation_options['id'],
            ]
        );
        $this->assertCount(
            1,
            $wc_products,
            'Actual size of the retrieved product collection is not equal to one'
        );

        // Handle the product update hook event
        $update_options = $this->handle_hook_request(
            self::UPDATE_DATADUMP_DIRECTORY,
            self::UPDATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
        );
        // Fetch the product that was created by the update hook
        $wc_products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $update_options['id'],
            ]
        );
        $this->assertCount(
            1,
            $wc_products,
            'Actual size of the retrieved product collection is not equal to one'
        );
        $wc_updated_product = $wc_products[0];

        // Get the updated data dump as a dotnotated collection
        $updated_file = $this->getDataDump(self::UPDATE_DATADUMP_DIRECTORY.'/'.self::UPDATE_DATADUMP_PRODUCT);
        $updated_product_data = $updated_file->getReturn()['data'];
        $updated_product_data = new Dot($updated_product_data[0]);

        // Compare the values of the updated data dump against the updated wordpress product
        $this->assertProduct($updated_product_data, $wc_updated_product);

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testOrderOnlySyncMode()
    {
        StoreKeeperOptions::set(StoreKeeperOptions::SYNC_MODE, StoreKeeperOptions::SYNC_MODE_ORDER_ONLY);

        $this->assertProductCount(0, 'Environment not empty');

        $product = WC_Helper_Product::create_simple_product(false);
        $product->set_sku('MD826ZM/A2');
        $product->set_stock_quantity(9001);
        $product->set_manage_stock(true);
        $product->set_backorders('yes');
        $product->save();

        $this->handle_hook_request(
            self::UPDATE_DATADUMP_DIRECTORY,
            self::UPDATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events',
            false
        );

        $this->assertTaskNotCount(0, 'Should have created more than zero task');

        // Process all the tasks
        $this->runner->execute(ProcessAllTasks::getCommandName());

        $product = wc_get_product($product);
        $this->assertNull($product->get_stock_quantity(), 'Stock quantity was not updated.');
        $this->assertFalse($product->get_manage_stock(), 'Stock manage was not updated.');
        $this->assertEquals('no', $product->get_backorders(), 'Stock backorders was not updated.');
        $this->assertEquals(self::WC_STATUS_INSTOCK, $product->get_stock_status(), 'Stock manage was not updated.');
    }

    private function assertProductCount(int $expected, string $message)
    {
        $this->assertCount(
            $expected,
            wc_get_products(
                [
                    'post_type' => 'product',
                ]
            ),
            $message
        );
    }

    public function testDeactivateProduct()
    {
        // Handle the product creation hook event so there is a product to deactivate
        $creation_options = $this->handle_hook_request(
            self::CREATE_DATADUMP_DIRECTORY,
            self::CREATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
        );
        // Fetch the product that was created by the creation hook
        $wc_products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $creation_options['id'],
            ]
        );
        $this->assertCount(
            1,
            $wc_products,
            'Actual size of the retrieved product collection is not equal to one'
        );

        $original_post_id = $wc_products[0]->get_id();

        // Handle the product deactivation hook
        $deactivation_options = $this->handle_hook_request(
            self::DEACTIVATE_DATADUMP_DIRECTORY,
            self::DEACTIVATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
        );

        // Try to retrieve the product from wordpress. Should return an empty array since the product has been removed
        $wc_products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $deactivation_options['id'],
            ]
        );
        $this->assertEmpty(
            $wc_products,
            'Product with the StoreKeeper id could still be retrieved from WooCommerce'
        );

        // Make sure that the product (post) is actually trashed
        $original_post_status = get_post_status($original_post_id);
        $this->assertEquals(
            self::POST_STATUS_TRASHED,
            $original_post_status,
            'WooCommerce post status doesn\'t match the expected post status'
        );

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testActivateProductNoInitial()
    {
        // Handle the product activation hook event
        $activation_options = $this->handle_hook_request(
            self::ACTIVATE_DATADUMP_DIRECTORY,
            self::ACTIVATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
        );
        // Fetch the product that was created by the activation hook
        $wc_products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $activation_options['id'],
            ]
        );
        $this->assertCount(
            1,
            $wc_products,
            'Actual size of the retrieved product collection is not equal to one'
        );

        $wc_created_product = $wc_products[0];

        // Get the data dump
        $activate_file = $this->getDataDump(self::ACTIVATE_DATADUMP_DIRECTORY.'/'.self::ACTIVATE_DATADUMP_PRODUCT);
        $activated_product_data = $activate_file->getReturn()['data'];
        $activated_product_data = new Dot($activated_product_data[0]);

        // Compare the values of the updated data dump against the updated wordpress product
        $this->assertProduct($activated_product_data, $wc_created_product);

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testActivateProductAfterDeactivating()
    {
        // see : https://app.clickup.com/t/3cp0b5?comment=38147526

        // Handle the product creation hook event so there is a product to deactivate
        $creation_options = $this->handle_hook_request(
            self::CREATE_DATADUMP_DIRECTORY,
            self::CREATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
        );
        // Fetch the product that was created by the creation hook
        $wc_products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $creation_options['id'],
            ]
        );
        $this->assertCount(
            1,
            $wc_products,
            'Actual size of the retrieved product collection is not equal to one'
        );

        // Handle the product deactivation hook
        $deactivation_options = $this->handle_hook_request(
            self::DEACTIVATE_DATADUMP_DIRECTORY,
            self::DEACTIVATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
        );

        // Try to retrieve the product from wordpress. Should return an empty array since the product has been removed
        $wc_products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $deactivation_options['id'],
            ]
        );
        $this->assertEmpty(
            $wc_products,
            'Product with the StoreKeeper id could still be retrieved from WooCommerce'
        );

        // Handle the product activation hook event
        $activation_options = $this->handle_hook_request(
            self::ACTIVATE_DATADUMP_DIRECTORY,
            self::ACTIVATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
        );
        // Fetch the product that was created by the activation hook
        $wc_products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $activation_options['id'],
            ]
        );
        $this->assertCount(
            1,
            $wc_products,
            'Actual size of the retrieved product collection is not equal to one'
        );
        $wc_created_product = $wc_products[0];

        // Get the data dump
        $activate_file = $this->getDataDump(self::ACTIVATE_DATADUMP_DIRECTORY.'/'.self::ACTIVATE_DATADUMP_PRODUCT);
        $activated_product_data = $activate_file->getReturn()['data'];
        $activated_product_data = new Dot($activated_product_data[0]);

        // Compare the values of the updated data dump against the updated wordpress product
        $this->assertProduct($activated_product_data, $wc_created_product);

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    /**
     * @param $datadump_dir
     * @param $datadump_file
     * @param $expected_backref
     * @param $expected_hook_action
     * @param $process_tasks
     *
     * @throws \Throwable
     */
    protected function handle_hook_request(
        $datadump_dir,
        $datadump_file,
        $expected_backref,
        $expected_hook_action,
        $process_tasks = true
    ): array {
        // Initialize the connection with the API
        $this->initApiConnection();

        // Setup the data dump
        $this->mockApiCallsFromDirectory($datadump_dir, true);
        $this->mockMediaFromDirectory(self::MEDIA_DATADUMP_DIRECTORY);
        $file = $this->getHookDataDump($datadump_file);

        // Check the backref of the product event
        $backref = $file->getEventBackref();
        list($main_type, $original_options) = StoreKeeperApi::extractMainTypeAndOptions($backref);
        $this->assertEquals(
            $expected_backref,
            $main_type,
            'WooCommerce event backref doesn\'t match the expected event backref'
        );

        // Check the hook action from the file
        $this->assertEquals(
            $expected_hook_action,
            $file->getHookAction(),
            'WooCommerce hook action doesn\'t match the expected hook action'
        );

        // Handle the request
        $rest = $this->getRestWithToken($file);
        $response = $this->handleRequest($rest);
        $response = $response->get_data();
        $this->assertTrue($response['success'], 'Request failed');

        if ($process_tasks) {
            $this->runner->execute(ProcessAllTasks::getCommandName());
        }

        return $original_options;
    }
}
