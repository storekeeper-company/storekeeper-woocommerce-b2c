<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use Exception;
use Mockery\MockInterface;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks\InfoHandler;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\OrderHandler;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;

class InfoHandlerTest extends AbstractTest
{
    public const DATA_DUMP_FOLDER = 'endpoints/webhooks/infoHandler';

    /**
     * @throws \Throwable
     */
    public function testHandleOk()
    {
        $this->initApiConnection();

        $this->mockApiCallsFromDirectory(self::DATA_DUMP_FOLDER, false);

        $file = $this->getHookDataDump('hook.info.json');
        $rest = $this->getRestWithToken($file);
        $this->assertEquals('info', $file->getHookAction());

        $this->assertPreConfiguration($rest);
        StoreKeeperOptions::set(StoreKeeperOptions::SHIPPING_METHOD_ACTIVATED, 'yes');
        $this->assertPostConfiguration($rest);
    }

    private function assertPreConfiguration(\WP_REST_Request $rest): void
    {
        $response = $this->handleRequest($rest);
        $data = $response->get_data();

        $extra = $data['extra'];
        $activeCapabilities = $extra['active_capability'];
        $this->assertNotContains('b2s_shipping_method', $activeCapabilities, 'Shipping method should not be a default capability');
    }

    private function assertPostConfiguration(\WP_REST_Request $rest): void
    {
        // These first 3 orders will fail because the woocommerce_currency is not `EUR`
        $firstFailedOrderId = $this->createWoocommerceOrder();
        $secondFailedOrderId = $this->createWoocommerceOrder();
        // Cancelled order will be ignored on info handler ids_with_failed_tasks
        $cancelledFailedOrderId = $this->createWoocommerceOrder(OrderExport::STATUS_CANCELLED);
        $this->exportOrder($firstFailedOrderId);
        $this->exportOrder($secondFailedOrderId);
        $this->exportOrder($cancelledFailedOrderId);
        update_option('woocommerce_currency', 'EUR');

        // This will be the expected last synchronized order and last task processed
        // based on the unit test logical order
        $successfulOrderExpectedId = $this->createWoocommerceOrder();

        $successfulOrderId = $this->createWoocommerceOrder();
        $this->exportOrder($successfulOrderId);
        $this->exportOrder($successfulOrderExpectedId);

        $expectedSuccessfulOrderTasks = TaskModel::findBy(
            [
                'type = :type',
                'storekeeper_id = :storekeeper_id',
            ],
            [
                'type' => TaskHandler::ORDERS_EXPORT,
                'storekeeper_id' => $successfulOrderExpectedId,
            ],
            'date_last_processed',
            'DESC',
            1,
        );

        $expectedSuccessfulOrderTask = reset($expectedSuccessfulOrderTasks);

        // Cancelled and refunded order will be ignored on info handler ids_not_synchronized
        $this->createWoocommerceOrder(OrderExport::STATUS_CANCELLED);
        $this->createWoocommerceOrder(OrderExport::STATUS_REFUNDED);
        $firstUnsynchronizedOrderId = $this->createWoocommerceOrder();
        $secondUnsynchronizedOrderId = $this->createWoocommerceOrder();

        $expectedFailedOrderIds = [
            $firstFailedOrderId,
            $secondFailedOrderId,
        ];

        $expectedIdsNotSynchronized = [
            $firstFailedOrderId,
            $secondFailedOrderId,
            $firstUnsynchronizedOrderId,
            $secondUnsynchronizedOrderId,
        ];

        $expectedOldestOrderNotSynchronized = wc_get_order($firstFailedOrderId);
        $expectedOldestDateNotSynchronized = $expectedOldestOrderNotSynchronized->get_date_created()->format(DATE_ATOM);

        $expectedLastOrder = wc_get_order($secondUnsynchronizedOrderId);
        $expectedLastOrderDate = $expectedLastOrder->get_date_created()->format(DATE_ATOM);

        $response = $this->handleRequest($rest);
        $data = $response->get_data();
        $extra = $data['extra'];

        $this->assertEquals(
            get_bloginfo('version'),
            $data['platform_version'],
            'Incorrect platform version'
        );

        // Check blog info fields
        $this->assertNotEmpty(
            $extra,
            'Missing extra fields'
        );
        foreach (InfoHandler::EXTRA_BLOG_INFO_FIELDS as $field) {
            $this->assertEquals(
                get_bloginfo($field),
                $extra[$field],
                "Check blog info field $field"
            );
        }

        // Check active theme
        $actualTheme = $extra['active_theme'];
        $this->assertNotEmpty(
            $actualTheme,
            'Missing active_theme in extra fields'
        );
        $expectedTheme = wp_get_theme();
        foreach (InfoHandler::EXTRA_ACTIVE_THEME_FIELD as $field) {
            $this->assertEquals($expectedTheme->get($field), $actualTheme[$field]);
        }

        // Check sync mode
        $this->assertEquals(
            StoreKeeperOptions::SYNC_MODE_FULL_SYNC,
            $extra['sync_mode'],
            'Incorrect sync mode'
        );

        $systemStatus = $extra['system_status'];
        $orderSystemStatus = $systemStatus['order'];
        $taskProcessorStatus = $systemStatus['task_processor'];
        $failedCompatibilityChecks = $systemStatus['failed_compatibility_checks'];

        // Assert system status

        // Assert order system status
        $this->assertEqualsCanonicalizing($expectedIdsNotSynchronized, $orderSystemStatus['ids_not_synchronized'], 'Not synchronized IDs should match with extras');
        $this->assertEqualsCanonicalizing($expectedFailedOrderIds, $orderSystemStatus['ids_with_failed_tasks'], 'Failed order IDs should match with extras');
        $this->assertEquals($expectedLastOrderDate, $orderSystemStatus['last_date']);
        $this->assertEquals(
            DatabaseConnection::formatFromDatabaseDate($expectedSuccessfulOrderTask['date_last_processed'])->format(DATE_ATOM),
            $orderSystemStatus['last_synchronized_date'],
            'Last successful synchronized order ID should match with extras'
        );
        $this->assertEquals(
            $expectedOldestDateNotSynchronized,
            $orderSystemStatus['oldest_date_not_synchronized'],
            'Oldest order unsynchronized date should match with extras'
        );

        // 7 tasks were queued because every single order created, 2 tasks were created
        // We are expecting 2 unsynchronized orders, and 1 cancelled unsynchronized order
        // So 3 orders x 2 tasks = 6 tasks
        // Then another task was created when one of the order's status was changed to cancelled
        // This is because of the updateWithIgnore hooks, see Core::setOrderHooks
        // Assert task processor
        // 3 extra tasks on refunded order
        $this->assertEquals(
            10,
            $taskProcessorStatus['in_queue_quantity'],
            'Task in queue should match with extras'
        );

        $this->assertEquals(
            DatabaseConnection::formatFromDatabaseDate($expectedSuccessfulOrderTask['date_last_processed'])->format(DATE_ATOM),
            $taskProcessorStatus['last_task_date'],
            'Last task ran date should match with extras'
        );

        // Assert failed compatibility checks
        $this->assertCount(0, $failedCompatibilityChecks, 'Failed compatibility checks should return 1 (woocommerce_manage_stock)');

        $activeCapabilities = $extra['active_capability'];
        $this->assertContains('b2s_shipping_method', $activeCapabilities, 'Shipping method should be active');
    }

    public function testFreshWebshop()
    {
        $this->initApiConnection();

        $this->mockApiCallsFromDirectory(self::DATA_DUMP_FOLDER, false);
        update_option('woocommerce_currency', 'EUR');

        $successfulOrderId = $this->createWoocommerceOrder();
        $this->exportOrder($successfulOrderId);

        $file = $this->getHookDataDump('hook.info.json');
        $rest = $this->getRestWithToken($file);

        $response = $this->handleRequest($rest);
        $data = $response->get_data();
        $extra = $data['extra'];
        $systemStatus = $extra['system_status'];
        $orderSystemStatus = $systemStatus['order'];

        // Assert order system status
        $this->assertCount(0, $orderSystemStatus['ids_not_synchronized'], 'Not synchronized IDs should match with extras');
        $this->assertCount(0, $orderSystemStatus['ids_with_failed_tasks'], 'Failed order IDs should match with extras');
    }

    /**
     * @throws \WC_Data_Exception
     */
    protected function createWoocommerceOrder(?string $status = null): int
    {
        $product = \WC_Helper_Product::create_simple_product();
        \WC_Helper_Shipping::create_simple_flat_rate();

        $orderData = [
            'status' => 'pending',
            'customer_id' => 1,
            'customer_note' => '',
            'total' => '',
        ];

        if (!is_null($status)) {
            $orderData['status'] = $status;
        }

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1'; // Required, else wc_create_order throws an exception
        $order = wc_create_order($orderData);

        // Add order products
        $item = new \WC_Order_Item_Product();
        $item->set_props(
            [
                'product' => $product,
                'quantity' => 4,
                'subtotal' => wc_get_price_excluding_tax($product, ['qty' => 4]),
                'total' => wc_get_price_excluding_tax($product, ['qty' => 4]),
            ]
        );
        $item->save();
        $order->add_item($item);

        // Set billing address
        $order->set_billing_first_name('Jeroen');
        $order->set_billing_last_name('Sormani');
        $order->set_billing_company('WooCompany');
        $order->set_billing_address_1('WooAddress');
        $order->set_billing_address_2('');
        $order->set_billing_city('WooCity');
        $order->set_billing_state('NY');
        $order->set_billing_postcode('123456');
        $order->set_billing_country('US');
        $order->set_billing_email('admin@example.org');
        $order->set_billing_phone('555-32123');

        // Add shipping costs
        $shipping_taxes = \WC_Tax::calc_shipping_tax('10', \WC_Tax::get_shipping_tax_rates());
        $rate = new \WC_Shipping_Rate('flat_rate_shipping', 'Flat rate shipping', '10', $shipping_taxes, 'flat_rate');
        $item = new \WC_Order_Item_Shipping();
        $item->set_props(
            [
                'method_title' => $rate->label,
                'method_id' => $rate->id,
                'total' => wc_format_decimal($rate->cost),
                'taxes' => $rate->taxes,
            ]
        );
        foreach ($rate->get_meta_data() as $key => $value) {
            $item->add_meta_data($key, $value, true);
        }
        $order->add_item($item);

        // Set payment gateway
        $payment_gateways = WC()->payment_gateways->payment_gateways();
        $order->set_payment_method($payment_gateways['bacs']);

        // Set totals
        $order->set_shipping_total(10);
        $order->set_discount_total(0);
        $order->set_discount_tax(0);
        $order->set_cart_tax(0);
        $order->set_shipping_tax(0);
        $order->set_total(50); // 4 x $10 simple helper product
        $order->save();

        return $order->get_id();
    }

    /**
     * @throws \Exception
     */
    protected function exportOrder(int $orderId): void
    {
        $orderHandler = new OrderHandler();
        $orderHandler->create($orderId);

        $storekeeperOrderId = mt_rand();
        $storekeeperCustomerId = mt_rand();

        StoreKeeperApi::$mockAdapter->withModule(
            'ShopModule',
            function (MockInterface $module) use ($storekeeperOrderId, $storekeeperCustomerId) {
                $module->allows('newOrder')
                    ->andReturnUsing(
                        function () use ($storekeeperOrderId) {
                            return $storekeeperOrderId;
                        }
                    );

                $module->allows('findShopCustomerBySubuserEmail')
                    ->andReturnUsing(
                        function () use ($storekeeperCustomerId) {
                            return [
                                'id' => $storekeeperCustomerId,
                            ];
                        }
                    );
                $module->allows('getOrder')
                    ->andReturnUsing(
                        function () use ($storekeeperOrderId) {
                            return [
                                'id' => $storekeeperOrderId,
                                'status' => OrderExport::STATUS_NEW,
                                'is_paid' => false,
                                'order_items' => [],
                            ];
                        }
                    );
                $module->allows('updateOrder')->andReturnUsing(
                    function () {
                        return null;
                    }
                );
            }
        );

        $this->runner->execute(ProcessAllTasks::getCommandName());
    }
}
