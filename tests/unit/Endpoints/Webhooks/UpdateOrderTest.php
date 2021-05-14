<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\CleanWoocommerceEnvironment;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Imports\OrderImport;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\CommandRunnerTrait;
use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;
use WC_Helper_Order;
use WC_Order;

class UpdateOrderTest extends AbstractTest
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

    public function createWooCommerceOrder(): int
    {
        // see https://github.com/woocommerce/woocommerce/blob/master/tests/framework/helpers/class-wc-helper-order.php as example
        $order = WC_Helper_Order::create_order();

        return $order->save();
    }

    /**
     * @param $dumpFile
     *
     * @return Dot
     */
    protected function getLastEventFromDumpfile($dumpFile)
    {
        // Get and check if the dumpFile has a body
        $rawData = $dumpFile->getData();
        $data = new Dot($rawData);

        $bodyRaw = $data->get('request.body');
        $this->assertNotEmpty($bodyRaw, 'Dumpfile has request.body');

        // Check if the dumpFile has events in its payload
        $body = new Dot(json_decode($bodyRaw, true));
        $events = $body->get('payload.events');

        $eventCount = count($events);
        $this->assertTrue($eventCount > 0, 'DumpFile has events');

        // Get the latest event
        $eventRaw = array_values($events)[$eventCount - 1];

        return new Dot($eventRaw);
    }

    public function testHandleUpdateOrder()
    {
        $this->initApiConnection();

        $this->executeOrderUpdateTest();

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testOrderOnlySyncMode()
    {
        $this->initApiConnection();

        StoreKeeperOptions::set(StoreKeeperOptions::SYNC_MODE, StoreKeeperOptions::SYNC_MODE_ORDER_ONLY);

        $this->executeOrderUpdateTest();

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    protected function executeOrderUpdateTest(): void
    {
        $this->syncShopInformation();

        // Create order
        $wc_order_id = $this->createWooCommerceOrder();

        // Clear the created order exports from the createOrder function
        CleanWoocommerceEnvironment::cleanTasks();

        // Get dump file and set storekeeper id on created order
        $dumpFile = $this->getHookDataDump('events/hook.events.updateOrder.json');

        // Check the backref of the category
        $backref = $dumpFile->getEventBackref();
        list($main_type, $options) = StoreKeeperApi::extractMainTypeAndOptions($backref);
        $this->assertEquals('ShopModule::Order', $main_type, 'Event type');
        $this->assertNotEmpty($options['id'], 'Option id exists');

        // We need to set the storekeeper ID on the order so it updates the order
        update_post_meta($wc_order_id, 'storekeeper_id', $options['id']);

        // Check hook action
        $rest = $this->getRestWithToken($dumpFile);
        $this->assertEquals('events', $dumpFile->getHookAction());

        // Check if successfull
        $response = $this->handleRequest($rest);
        $response = $response->get_data();
        $this->assertTrue($response['success'], 'Request failed');

        // Update order
        $this->runner->execute(ProcessAllTasks::getCommandName());

        // Assert the status
        $wc_order = new WC_Order($wc_order_id);
        $wc_status = $wc_order->get_status('edit');

        // get last storekeeper order
        $event = $this->getLastEventFromDumpfile($dumpFile);
        $storekeeper_raw_status = $event->get('details.order.status');

        $this->assertNotEmpty($storekeeper_raw_status, 'dumpFile\'s last event has an order.status');
        $storekeeper_status = OrderImport::getWoocommerceStatus($storekeeper_raw_status);

        // assert the status
        $this->assertEquals($storekeeper_status, $wc_status, 'Order status update');
    }
}
