<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use Adbar\Dot;
use Mockery\MockInterface;
use StoreKeeper\WooCommerce\B2C\Commands\CleanWoocommerceEnvironment;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Imports\OrderImport;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\PaymentGateway\PaymentGateway;
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

    public function dataProviderOrderRefund(): array
    {
        $data = [];

        $data['backoffice order without refund'] = [
            'paidValueWt' => 50.00,
            'paidBackValueWt' => 0,
            'refundedPriceWt' => 50.00,
            'firstExpectedRefundCount' => 0,
            'secondExpectedRefundCount' => 1,
        ];

        $data['backoffice order with refund'] = [
            'paidValueWt' => 50.00,
            'paidBackValueWt' => 50.00,
            'refundedPriceWt' => 50.00,
            'firstExpectedRefundCount' => 0,
            'secondExpectedRefundCount' => 0,
        ];

        $data['backoffice order with partial refund'] = [
            'paidValueWt' => 50.00,
            'paidBackValueWt' => 35.00,
            'refundedPriceWt' => 35.00,
            'firstExpectedRefundCount' => 0,
            'secondExpectedRefundCount' => 1,
        ];

        return $data;
    }

    /**
     * @dataProvider dataProviderOrderRefund
     */
    public function testOrderRefund($paidValueWt, $paidBackValueWt, $refundedPriceWt, $firstExpectedRefundCount, $secondExpectedRefundCount)
    {
        $this->initApiConnection();

        StoreKeeperApi::$mockAdapter->withModule(
            'ShopModule',
            function (MockInterface $module) use ($paidValueWt, $paidBackValueWt, $refundedPriceWt) {
                $module->allows('getOrder')->andReturnUsing(
                    function ($got) use ($paidValueWt, $paidBackValueWt, $refundedPriceWt) {
                        return [
                            'paid_value_wt' => $paidValueWt,
                            'paid_back_value_wt' => $paidBackValueWt,
                            'refunded_price_wt' => $refundedPriceWt,
                        ];
                    }
                );
            }
        );

        $wooCommmerceOrderId = $this->createWooCommerceOrder();
        CleanWoocommerceEnvironment::cleanTasks();
        $dumpFile = $this->getHookDataDump('events/updateOrder/hook.events.updateOrderRefundedStatus.json');
        $backref = $dumpFile->getEventBackref();
        [, $options] = StoreKeeperApi::extractMainTypeAndOptions($backref);
        update_post_meta($wooCommmerceOrderId, 'storekeeper_id', $options['id']);

        // Check hook action
        $rest = $this->getRestWithToken($dumpFile);
        $this->handleRequest($rest);
        $this->runner->execute(ProcessAllTasks::getCommandName());
        $this->assertFalse(PaymentGateway::$refundedBySkStatus, 'Should be false after import');

        // Assert the status
        $wooCommerceOrder = new WC_Order($wooCommmerceOrderId);
        $wooCommerceStatus = $wooCommerceOrder->get_status('edit');

        // get last storekeeper order
        $event = $this->getLastEventFromDumpfile($dumpFile);
        $storeKeeperRawStatus = $event->get('details.order.status');

        $this->assertNotEmpty($storeKeeperRawStatus, 'Dump file\'s last event does not have an order.status');
        $storeKeeperStatus = OrderImport::getWoocommerceStatus($storeKeeperRawStatus);
        $this->assertEquals($storeKeeperStatus, $wooCommerceStatus, 'Order status was not updated');

        $refunds = $wooCommerceOrder->get_refunds();

        $this->assertCount($firstExpectedRefundCount, $refunds, 'Refund of exactly '.$firstExpectedRefundCount.' should be created');
        // Assert refunds to be synchronized in backoffice
        $this->assertCount($firstExpectedRefundCount, PaymentGateway::getUnsyncedRefundsWithoutPaymentIds($wooCommmerceOrderId), $firstExpectedRefundCount.' refunds to be synchronized should be found');

        // Create a refund/same as creation of external like Mollie
        wc_create_refund([
            'order_id' => $wooCommerceOrder->get_id(),
            'amount' => 50,
        ]);

        $refunds = $wooCommerceOrder->get_refunds();
        $this->assertCount($secondExpectedRefundCount, $refunds, 'Refund of exactly '.$secondExpectedRefundCount.' should be created');
        // Assert refunds to be synchronized in backoffice
        $this->assertCount($secondExpectedRefundCount, PaymentGateway::getUnsyncedRefundsWithoutPaymentIds($wooCommmerceOrderId), $secondExpectedRefundCount.' refunds to be synchronized should be found');
    }

    protected function executeOrderUpdateTest(): void
    {
        $this->syncShopInformation();

        $expectedOrderStatusUrl = 'https://test.storekeepercloud.com/apps/order-status/unit-test';
        StoreKeeperApi::$mockAdapter->withModule(
            'ShopModule',
            function (MockInterface $module) use ($expectedOrderStatusUrl) {
                $module->shouldReceive('getOrderStatusPageUrl')->andReturnUsing(
                    function ($got) use ($expectedOrderStatusUrl) {
                        return $expectedOrderStatusUrl;
                    }
                );
            }
        );

        StoreKeeperApi::$mockAdapter->withModule(
            'ShopModule',
            function (MockInterface $module) {
                $module->shouldReceive('getOrder')->andReturnUsing(
                    function ($got) {
                        return [
                            'shipped_item_no' => 1, // Simply mock shipped item to test order status URL
                        ];
                    }
                );
            }
        );

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

        // assert the order status url
        $actualOrderStatusUrl = $wc_order->get_meta(OrderImport::ORDER_PAGE_META_KEY, true);
        $this->assertEquals($expectedOrderStatusUrl, $actualOrderStatusUrl, 'Order status URL should be saved to order meta');
    }
}
