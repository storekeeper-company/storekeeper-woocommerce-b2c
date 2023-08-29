<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Imports\MenuItemImport;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\CommandRunnerTrait;
use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;
use WC_Helper_Product;

class MenuItemTest extends AbstractTest
{
    use CommandRunnerTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpRunner();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->tearDownRunner();
    }

    /**
     * Create.
     */
    const CREATE_DUMP_DIR = 'events/createMenuItem';
    const CREATE_DUMP_FILE = 'moduleFunction.ShopModule::listMenuItemsForHooks.success.json';
    const CREATE_DUMP_HOOK = 'events/hook.events.createMenuItem.json';

    /**
     * Delete.
     */
    const DELETE_DUMP_HOOK = 'events/hook.events.deleteMenuItem.json';

    /**
     * Update.
     *
     * @note: Update Hook file and moduleFunction file are in: <DUMP_DIR>/<TYPE>/<FILE>
     */
    const UPDATE_DUMP_DIR = 'events/updateMenuItem';
    const UPDATE_DUMP_HOOK = 'hook.events.updateMenuItem.json';
    const UPDATE_DUMP_FILE = 'moduleFunction.ShopModule::listMenuItemsForHooks.success.json';

    /**
     * Update types.
     */
    const UPDATE_TYPE_CATEGORY = 'category';
    const UPDATE_TYPE_LINK = 'link';
    const UPDATE_TYPE_PRODUCT = 'product';
    const UPDATE_TYPE_SPACER = 'spacer';

    public function testCreate()
    {
        $this->initApiConnection();

        $this->createMenuItem();

        $file = $this->getDataDump(self::CREATE_DUMP_DIR.'/'.self::CREATE_DUMP_FILE);
        $original_data = $file->getReturn()['data'][0];
        $original = new Dot($original_data);

        $current_menu_data = (array) MenuItemImport::getMenuTerm($original->get('menu_id'));
        $current_menu = new Dot($current_menu_data);

        $current_item_data = (array) MenuItemImport::getMenuItem($original->get('id'));
        $current_item = new Dot($current_item_data);

        $this->assertMenu($original, $current_menu);
        $this->assertMenuItem($original, $current_item);

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testUpdateLink()
    {
        $this->initApiConnection();

        $this->createMenuItem();

        $this->updateMenuItem(self::UPDATE_TYPE_LINK);
        $this->assertUpdateByType(self::UPDATE_TYPE_LINK);

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testUpdateProduct()
    {
        $this->initApiConnection();

        $this->createMenuItem();

        $this->updateMenuItem(self::UPDATE_TYPE_PRODUCT);
        $this->assertUpdateByType(self::UPDATE_TYPE_PRODUCT);

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testUpdateCategory()
    {
        $this->initApiConnection();

        $this->createMenuItem();

        $this->updateMenuItem(self::UPDATE_TYPE_CATEGORY);
        $this->assertUpdateByType(self::UPDATE_TYPE_CATEGORY);

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testUpdateSpacer()
    {
        $this->initApiConnection();

        $this->createMenuItem();

        $this->updateMenuItem(self::UPDATE_TYPE_SPACER);
        $this->assertUpdateByType(self::UPDATE_TYPE_SPACER);

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testOrderOnlySyncMode()
    {
        $this->initApiConnection();

        StoreKeeperOptions::set(StoreKeeperOptions::SYNC_MODE, StoreKeeperOptions::SYNC_MODE_ORDER_ONLY);

        $this->createMenuItem(false);
        $this->assertTaskCount(0, 'No tasks are supposed to be created');
        $this->runner->execute(ProcessAllTasks::getCommandName());

        $this->updateMenuItem(self::UPDATE_TYPE_LINK);
        $this->updateMenuItem(self::UPDATE_TYPE_CATEGORY);
        $this->updateMenuItem(self::UPDATE_TYPE_PRODUCT);
        $this->updateMenuItem(self::UPDATE_TYPE_SPACER);

        $this->assertEmptyByType(self::UPDATE_TYPE_LINK);
        $this->assertEmptyByType(self::UPDATE_TYPE_CATEGORY);
        $this->assertEmptyByType(self::UPDATE_TYPE_PRODUCT);
        $this->assertEmptyByType(self::UPDATE_TYPE_SPACER);
    }

    public function testDelete()
    {
        $this->initApiConnection();

        $this->createMenuItem();

        $this->deleteMenuItem();

        $file = $this->getDataDump(self::CREATE_DUMP_DIR.'/'.self::CREATE_DUMP_FILE);
        $original_data = $file->getReturn()['data'][0];
        $original = new Dot($original_data);

        $current_menu_data = (array) MenuItemImport::getMenuTerm($original->get('menu_id'));
        $current_menu = new Dot($current_menu_data);

        $this->assertMenu($original, $current_menu);
        $this->assertNull(
            MenuItemImport::getMenuItem($original->get('id')),
            'Test if the menu item was really removed'
        );

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    private function assertMenu($original, $current_menu)
    {
        $menu_storekeeper_id = get_term_meta($current_menu->get('term_id'), 'menu_storekeeper_id', true);

        $this->assertNotEmpty(
            $current_menu->get('term_id'),
            'Test if the menu was imported'
        );
        $this->assertEquals(
            $original->get('menu_id'),
            $menu_storekeeper_id,
            'Test if the menu has the correct storekeeper_id'
        );
        $this->assertEquals(
            $original->get('menu.alias'),
            $current_menu->get('name'),
            'Test if the menu has the correct name'
        );
    }

    private function assertMenuItem($original, $current_item)
    {
        $menu_item_storekeeper_id = get_post_meta($current_item->get('ID'), 'menu_item_storekeeper_id', true);
        $vars = MenuItemImport::getMenuItemConfig($original);
        $expected_menu_item_url = $vars['menu-item-url'];
        $current_menu_item_url = get_post_meta($current_item->get('ID'), '_menu_item_url', true);

        $this->assertNotEmpty(
            $current_item->get('ID'),
            'Test if the menu item was imported'
        );
        $this->assertEquals(
            $original->get('id'),
            $menu_item_storekeeper_id,
            'Test if the menu item has the correct storekeeper_id'
        );
        $this->assertEquals(
            $original->get('title'),
            $current_item->get('post_title'),
            'Test if the menu item has the correct title'
        );
        $this->assertEquals(
            $expected_menu_item_url,
            $current_menu_item_url,
            'Test if the menu item has the correct url'
        );
    }

    private function assertUpdateByType($type)
    {
        $file = $this->getDataDump(self::UPDATE_DUMP_DIR.'/'.$type.'/dump/'.self::CREATE_DUMP_FILE);
        $original_data = $file->getReturn()['data'][0];
        $original = new Dot($original_data);

        $current_menu_data = (array) MenuItemImport::getMenuTerm($original->get('menu_id'));
        $current_menu = new Dot($current_menu_data);

        $current_item_data = (array) MenuItemImport::getMenuItem($original->get('id'));
        $current_item = new Dot($current_item_data);

        $this->assertMenu($original, $current_menu);
        $this->assertMenuItem($original, $current_item);
    }

    private function assertEmptyByType($type)
    {
        $file = $this->getDataDump(self::UPDATE_DUMP_DIR.'/'.$type.'/dump/'.self::CREATE_DUMP_FILE);
        $original_data = $file->getReturn()['data'][0];
        $original = new Dot($original_data);

        $current_menu = MenuItemImport::getMenuTerm($original->get('menu_id'));
        $current_item = MenuItemImport::getMenuItem($original->get('id'));

        $this->assertNull($current_menu, 'Menu should not be imported');
        $this->assertNull($current_item, 'Menu item should not be imported');
    }

    private function createMenuItem(bool $process_tasks = true)
    {
        $this->runAndExecuteHook(self::CREATE_DUMP_HOOK, self::CREATE_DUMP_DIR, $process_tasks);
    }

    private function updateMenuItem($type)
    {
        $dumpDir = self::UPDATE_DUMP_DIR.'/'.$type.'/dump';
        $hookFile = self::UPDATE_DUMP_DIR.'/'.$type.'/'.self::UPDATE_DUMP_HOOK;

        $this->createImportTarget($type);

        $this->runAndExecuteHook($hookFile, $dumpDir);
    }

    private function createImportTarget($type)
    {
        $filePath = self::UPDATE_DUMP_DIR.'/'.$type.'/dump/'.self::UPDATE_DUMP_FILE;
        $file = $this->getDataDump($filePath);
        $menuItem = $file->getReturn()['data'][0];
        $vars = MenuItemImport::processItemVars($menuItem['menu_item_vars']);
        switch ($type) {
            case self::UPDATE_TYPE_PRODUCT:
                $product = WC_Helper_Product::create_simple_product(true);
                update_post_meta($product->get_id(), 'storekeeper_id', $vars['shop_product_id']);
                break;
            case self::UPDATE_TYPE_LINK:
                break;
            case self::UPDATE_TYPE_CATEGORY:
                $category = WC_Helper_Product::create_product_category();
                update_term_meta($category->term_id, 'storekeeper_id', $vars['category_id']);
                break;
            case self::UPDATE_TYPE_SPACER:
                break;
        }
    }

    private function deleteMenuItem()
    {
        $this->runAndExecuteHook(self::DELETE_DUMP_HOOK);
    }

    private function runAndExecuteHook($hook_file, $mock_dir = null, bool $process_tasks = true)
    {
        // Setup mock dir if needed
        if ($mock_dir) {
            $this->mockApiCallsFromDirectory($mock_dir, true);
        }

        // Get hook file
        $hookFile = $this->getHookDataDump($hook_file);

        // Get rest response & create tasks
        $rest = $this->getRestWithToken($hookFile);
        $response = $this->handleRequest($rest);
        $response = $response->get_data();

        // Get hook_type
        list($hook_type) = StoreKeeperApi::extractMainTypeAndOptions($hookFile->getEventBackref());

        // Tests
        $this->assertTrue(
            $response['success'],
            'Hook call successfull'
        );
        $this->assertEquals(
            'events',
            $hookFile->getHookAction(),
            'Hook actions'
        );
        $this->assertEquals(
            'BlogModule::MenuItem',
            $hook_type,
            'Correct hook type'
        );

        if ($process_tasks) {
            $this->runner->execute(ProcessAllTasks::getCommandName());
        }
    }
}
