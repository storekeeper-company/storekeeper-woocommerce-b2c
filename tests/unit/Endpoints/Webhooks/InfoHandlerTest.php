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
use Throwable;

class InfoHandlerTest extends AbstractTest
{
    const DATA_DUMP_FOLDER = 'endpoints/webhooks/infoHandler';

    /**
     * @throws Throwable
     */
    public function testHandleOk()
    {
        $this->initApiConnection();

        $this->mockApiCallsFromDirectory(self::DATA_DUMP_FOLDER, false);

        $file = $this->getHookDataDump('hook.info.json');
        $rest = $this->getRestWithToken($file);
        $this->assertEquals('info', $file->getHookAction());

        $this->assertPreConfiguration($rest);
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
        StoreKeeperOptions::set(StoreKeeperOptions::SHIPPING_METHOD_USED, 'yes');
        // These first 2 orders will fail because the woocommerce_currency is not `EUR`
        $firstFailedOrderId = $this->createWoocommerceOrder();
        $secondFailedOrderId = $this->createWoocommerceOrder();
        $this->exportOrder($firstFailedOrderId);
        $this->exportOrder($secondFailedOrderId);
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
        $expectedOldestDateNotSynchronized = $expectedOldestOrderNotSynchronized->get_date_created()->format(DATE_RFC2822);

        $expectedLastOrder = wc_get_order($secondUnsynchronizedOrderId);
        $expectedLastOrderDate = $expectedLastOrder->get_date_created()->format(DATE_RFC2822);

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
            DatabaseConnection::formatFromDatabaseDate($expectedSuccessfulOrderTask['date_last_processed'])->format(DATE_RFC2822),
            $orderSystemStatus['last_synchronized_date'],
            'Last successful synchronized order ID should match with extras'
        );
        $this->assertEquals(
            $expectedOldestDateNotSynchronized,
            $orderSystemStatus['oldest_date_not_synchronized'],
            'Oldest order unsynchronized date should match with extras'
        );

        // 6 tasks were queued because every single order created, 3 tasks were created
        // We are expecting 2 unsynchronized orders, so 2 orders x 3 tasks = 6 tasks
        // This is because of the updateWithIgnore hooks, see Core::setOrderHooks
        // Assert task processor
        $this->assertEquals(
            6,
            $taskProcessorStatus['in_queue_quantity'],
            'Task in queue should match with extras'
        );

        $this->assertEquals(
            DatabaseConnection::formatFromDatabaseDate($expectedSuccessfulOrderTask['date_last_processed'])->format(DATE_RFC2822),
            $taskProcessorStatus['last_task_date'],
            'Last task ran date should match with extras'
        );

        // Assert failed compatibility checks
        $this->assertCount(0, $failedCompatibilityChecks, 'Failed compatibility checks should return 1 (woocommerce_manage_stock)');

        $activeCapabilities = $extra['active_capability'];
        $this->assertContains('b2s_shipping_method', $activeCapabilities, 'Shipping method should be active');
    }

    protected function createWoocommerceOrder(): int
    {
        return \WC_Helper_Order::create_order()->save();
    }

    /**
     * @throws Exception
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
