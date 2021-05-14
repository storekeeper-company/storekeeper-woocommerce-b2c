<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\CommandRunnerTrait;
use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;

class EventHandlerTest extends AbstractTest
{
    use CommandRunnerTrait;

    public function setUp()
    {
        parent::setUp();
        $this->setUpRunner();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->tearDownRunner();
    }

    /**
     * test if init works.
     *
     * @throws \Throwable
     */
    public function testUpdateProduct()
    {
        $this->initApiConnection();

        $this->mockApiCallsFromDirectory('events/products/updateProduct', true);
        $this->mockMediaFromDirectory('events/products/media');
        $file = $this->getHookDataDump('events/hook.events.updateProduct.json');

        // check is the product is created
        $backref = $file->getEventBackref();
        list($main_type, $options) = StoreKeeperApi::extractMainTypeAndOptions($backref);
        $this->assertEquals('ShopModule::ShopProduct', $main_type, 'event type');

        $rest = $this->getRestWithToken($file);
        $this->assertEquals('events', $file->getHookAction());

        $response = $this->handleRequest($rest);
        $response = $response->get_data();
        $this->assertTrue($response['success'], 'request failed');

        // process the tasks
        $this->runner->execute(ProcessAllTasks::getCommandName());

        $product = ProductImport::findBackendShopProductId($options['id']);
        $this->assertNotFalse($product, 'product created');

        $used_keys = StoreKeeperApi::$mockAdapter->getUsedReturns();
        $this->assertCount(3, $used_keys, 'call made');
    }
}
