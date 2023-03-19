<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Exports;

use Adbar\Dot;
use Exception;
use Mockery\MockInterface;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\Models\PaymentModel;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\PaymentGateway\PaymentGateway;
use StoreKeeper\WooCommerce\B2C\PaymentGateway\StoreKeeperBaseGateway;
use StoreKeeper\WooCommerce\B2C\Tools\OrderHandler;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class OrderPaymentTest extends AbstractOrderExportTest
{
    const DATA_DUMP_FOLDER_NEW_ORDER = 'exports/orderExports/newOrder';
    const DATA_DUMP_FILE_FETCH_PRODUCTS = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.70cd1894eb5198a400f7b3ef90247685742de16d06a4ac86ba12632daa3d1179.json';

    const HAPPY_FLOW_STOREKEEPER_PAYMENT_FOLDER = 'exports/orderStoreKeeperPayments/normal';
    const HAPPY_FLOW_STOREKEEPER_PAYMENT_FILE_NEW = 'moduleFunction.ShopModule::newWebShopPaymentWithReturn.success.5e6ed30d4850d.json';
    const HAPPY_FLOW_STOREKEEPER_PAYMENT_FILE_SYNC = 'moduleFunction.ShopModule::syncWebShopPaymentWithReturn.success.5e6ed3419aaf7.json';

    const CANCELLED_STOREKEEPER_PAYMENT_FOLDER = 'exports/orderStoreKeeperPayments/cancelledPayment';
    const CANCELLED_STOREKEEPER_PAYMENT_FILE_CANCELLED_NEW = 'cancelled.moduleFunction.ShopModule::newWebShopPaymentWithReturn.success.5e828ed36afac.json';
    const CANCELLED_STOREKEEPER_PAYMENT_FILE_CANCELLED_SYNC = 'cancelled.moduleFunction.ShopModule::syncWebShopPaymentWithReturn.success.5e828edb43663.json';
    const CANCELLED_STOREKEEPER_PAYMENT_FILE_PAID_NEW = 'paid.moduleFunction.ShopModule::newWebShopPaymentWithReturn.success.5e828ee6bbc6f.json';
    const CANCELLED_STOREKEEPER_PAYMENT_FILE_PAID_SYNC = 'paid.moduleFunction.ShopModule::syncWebShopPaymentWithReturn.success.5e828f0e8aeea.json';

    const GET_CONTEXT = 'edit';

    /**
     * @var \StoreKeeper\ApiWrapper\ApiWrapper
     */
    private $api;

    public function setUp()
    {
        parent::setUp();
        StoreKeeperOptions::set(StoreKeeperOptions::PAYMENT_GATEWAY_ACTIVATED, 'yes');
        $this->initApiConnection();
        $this->api = StoreKeeperApi::getApiByAuthName();
    }

    public function testCancelledPayment()
    {
        $this->initApiConnection();

        // init check
        $this->emptyEnvironment();

        // Make an order
        $wc_order_id = $this->createWooCommerceOrder();
        $wc_order = wc_get_order($wc_order_id);

        // Payment constants
        $sk_cancelled_payment_id = rand();
        $sk_cancelled_method_id = rand();
        $sk_paid_payment_id = rand();
        $sk_paid_method_id = rand();

        // other constants
        $sk_order_id = rand();
        $sk_customer_id = rand();

        // Variables
        $OrderHandler = new OrderHandler();
        $updateStatusCount = 0;
        $initialStatus = $wc_order->get_status(self::WC_CONTEXT_EDIT);
        $getOrderStatus = OrderExport::STATUS_NEW; // initial getOrder status return

        // Mockup tasks
        StoreKeeperApi::$mockAdapter
            ->withModule(
                'ShopModule',
                function (MockInterface $module) use (
                    $sk_cancelled_payment_id,
                    $sk_cancelled_method_id,
                    $sk_paid_payment_id,
                    $sk_paid_method_id,
                    $sk_order_id,
                    $sk_customer_id,
                    $wc_order,
                    &$updateStatusCount,
                    &$getOrderStatus
                ) {
                    /*
                     * ShopModule::newWebShopPaymentWithReturn
                     */
                    $module->shouldReceive('newWebShopPaymentWithReturn')
                        ->andReturnUsing(
                            function ($parameters) use (
                                $sk_cancelled_payment_id,
                                $sk_cancelled_method_id,
                                $sk_paid_payment_id,
                                $sk_paid_method_id,
                                $wc_order
                            ) {
                                $methodId = (int) $parameters[0]['provider_method_id'];

                                if ($methodId === $sk_cancelled_method_id) {
                                    // Cancelled payment
                                    $filename = self::CANCELLED_STOREKEEPER_PAYMENT_FILE_CANCELLED_NEW;
                                    $paymentId = $sk_cancelled_payment_id;
                                } else {
                                    if ($methodId === $sk_paid_method_id) {
                                        // paid payment
                                        $filename = self::CANCELLED_STOREKEEPER_PAYMENT_FILE_PAID_NEW;
                                        $paymentId = $sk_paid_payment_id;
                                    } else {
                                        throw new Exception("Unknown method id=$methodId");
                                    }
                                }

                                // Prepare return
                                $file = $this->getDataDump(self::CANCELLED_STOREKEEPER_PAYMENT_FOLDER.'/'.$filename);
                                $data = $file->getReturn();
                                $data['amount'] = $wc_order->get_total(self::GET_CONTEXT);
                                $data['id'] = $paymentId;

                                return $data;
                            }
                        );

                    /*
                     * ShopModule::syncWebShopPaymentWithReturn
                     */
                    $module->shouldReceive('syncWebShopPaymentWithReturn')
                        ->andReturnUsing(
                            function ($parameters) use (
                                $sk_cancelled_payment_id,
                                $sk_paid_payment_id,
                                $wc_order
                            ) {
                                $paymentId = (int) $parameters[0];

                                // Get correct data
                                if ($paymentId === $sk_cancelled_payment_id) {
                                    // Cancelled payment
                                    $filename = self::CANCELLED_STOREKEEPER_PAYMENT_FILE_CANCELLED_SYNC;
                                } else {
                                    if ($paymentId === $sk_paid_payment_id) {
                                        // Paid payment
                                        $filename = self::CANCELLED_STOREKEEPER_PAYMENT_FILE_PAID_SYNC;
                                    } else {
                                        throw new Exception("Unknown payment id=$paymentId");
                                    }
                                }

                                // Prepare return
                                $file = $this->getDataDump(self::CANCELLED_STOREKEEPER_PAYMENT_FOLDER.'/'.$filename);
                                $data = $file->getReturn();
                                $data['amount'] = $wc_order->get_total(self::GET_CONTEXT);
                                $data['id'] = $paymentId;

                                return $data;
                            }
                        );

                    /*
                     * Non-related calls
                     */
                    $module->shouldReceive('getOrder')->andReturnUsing(
                        function ($got) use ($sk_order_id, &$getOrderStatus) {
                            return [
                                'id' => $sk_order_id,
                                'status' => $getOrderStatus,
                                'is_paid' => false,
                                'order_items' => [],
                            ];
                        }
                    );

                    $module->shouldReceive('updateOrder')->andReturnUsing(
                        function () {
                            return null;
                        }
                    );

                    $module->shouldReceive('updateOrderStatus')->andReturnUsing(
                        function ($got) use (&$updateStatusCount, &$getOrderStatus) {
                            ++$updateStatusCount;
                            $getOrderStatus = $got[0]['status']; // Update the next getOrder status
                        }
                    );

                    $module->shouldReceive('attachPaymentIdsToOrder')->andReturnUsing(
                        function ($got) use ($sk_order_id) {
                            return $sk_order_id;
                        }
                    );

                    $module->shouldReceive('findShopCustomerBySubuserEmail')->andReturnUsing(
                        function ($got) use ($sk_customer_id) {
                            return ['id' => $sk_customer_id];
                        }
                    );

                    $module->shouldReceive('naturalSearchShopFlatProductForHooks')->andReturnUsing(
                        function ($got) {
                            $file = $this->getDataDump(
                                self::DATA_DUMP_FOLDER_NEW_ORDER.'/'.self::DATA_DUMP_FILE_FETCH_PRODUCTS
                            );

                            return $file->getReturn();
                        }
                    );

                    $module->shouldReceive('newOrder')->andReturnUsing(
                        function ($got) use ($sk_order_id) {
                            return $sk_order_id;
                        }
                    );
                }
            );

        /*
         * cancelled payment on order.
         */
        $OrderHandler->create($wc_order_id);

        $this->createPaymentForMethodId($sk_cancelled_method_id, $wc_order, 'Create cancelled payment');
        $OrderHandler->create($wc_order_id);

        $paymentGateway = new PaymentGateway();
        $paymentGateway->checkPayment($wc_order->get_id());

        $OrderHandler->create($wc_order_id);
        $this->processAllTasks();

        $wc_order = wc_get_order($wc_order->get_id()); // Refresh wc_order

        $this->assertFalse(
            $wc_order->is_paid(),
            'Order is paid'
        );
        $this->assertEquals(
            $initialStatus,
            $wc_order->get_status(self::WC_CONTEXT_EDIT),
            'Order status changed'
        );

        /*
         * paid payment on order.
         */
        $this->createPaymentForMethodId($sk_paid_method_id, $wc_order, 'Create paid payment');
        $OrderHandler->create($wc_order_id);
        $paymentGateway = new PaymentGateway();
        $paymentGateway->checkPayment($wc_order->get_id());
        $OrderHandler->create($wc_order_id);

        $this->processAllTasks();

        /**
         * Assert paid payment on order.
         */
        $wc_order = wc_get_order($wc_order->get_id()); // Refresh wc_order

        $this->assertTrue(
            $wc_order->is_paid(),
            'Order is paid'
        );
        $this->assertEquals(
            StoreKeeperBaseGateway::ORDER_STATUS_PROCESSING,
            $wc_order->get_status(self::WC_CONTEXT_EDIT),
            'Order status is not processing'
        );
        $this->assertEquals(
            1,
            $updateStatusCount,
            'Order status changed too often'
        );
        $orderPayments = PaymentModel::findOrderPayments($wc_order->get_id());
        $this->assertEquals(
            2,
            count($orderPayments),
            'Both orders ware saved'
        );
        $this->assertTrue(
            PaymentModel::isAllPaymentInSync($wc_order_id),
            'All is synched'
        );
    }

    public function testOrderStoreKeeperPayment()
    {
        $this->initApiConnection();

        // init check
        $this->emptyEnvironment();

        // Make an order
        $new_order_id = $this->createWooCommerceOrder();
        $new_order = wc_get_order($new_order_id);

        // Constants
        $OrderHandler = new OrderHandler();
        $sk_payment_id = rand();
        $sk_provider_method_id = rand();
        $sk_order_id = rand();
        $sk_customer_id = rand();
        $getOrderStatus = OrderExport::STATUS_NEW;

        $updateStatusCount = 0;

        // Setup calls
        StoreKeeperApi::$mockAdapter->withModule(
            'ShopModule',
            function (MockInterface $module) use (
                $new_order,
                $sk_order_id,
                $sk_customer_id,
                $sk_payment_id,
                $sk_provider_method_id,
                $new_order_id,
                &$updateStatusCount,
                &$getOrderStatus
            ) {
                $module->shouldReceive('newWebShopPaymentWithReturn')->andReturnUsing(
                    function ($got) use (
                        $new_order,
                        $sk_payment_id,
                        $sk_provider_method_id,
                        $sk_customer_id
                    ) {
                        $payment = $got[0];
                        $this->assertEquals(
                            $sk_provider_method_id,
                            $payment['provider_method_id'],
                            'Check provider id'
                        );
                        $this->assertEquals(
                            $new_order->get_total(self::GET_CONTEXT),
                            $payment['amount'],
                            'Check order amount'
                        );
                        $this->assertEquals(
                            $sk_customer_id,
                            $payment['relation_data_id'],
                            'Check relation data id'
                        );

                        $this->assertRelationDataSnapshot($new_order, $payment['relation_data_snapshot']);
                        $this->assertErrorReportTask();

                        // return data
                        $file = $this->getDataDump(
                            self::HAPPY_FLOW_STOREKEEPER_PAYMENT_FOLDER.'/'.self::HAPPY_FLOW_STOREKEEPER_PAYMENT_FILE_NEW
                        );
                        $data = $file->getReturn();
                        $data['amount'] = $new_order->get_total(self::GET_CONTEXT);
                        $data['id'] = $sk_payment_id;

                        return $data;
                    }
                );

                $module->shouldReceive('syncWebShopPaymentWithReturn')->andReturnUsing(
                    function ($got) use ($new_order, $sk_payment_id) {
                        $file = $this->getDataDump(
                            self::HAPPY_FLOW_STOREKEEPER_PAYMENT_FOLDER.'/'.self::HAPPY_FLOW_STOREKEEPER_PAYMENT_FILE_SYNC
                        );
                        $data = $file->getReturn();
                        $data['amount'] = $new_order->get_total(self::GET_CONTEXT);
                        $data['id'] = $sk_payment_id;

                        return $data;
                    }
                );

                $module->shouldReceive('naturalSearchShopFlatProductForHooks')->andReturnUsing(
                    function ($got) {
                        $file = $this->getDataDump(
                            self::DATA_DUMP_FOLDER_NEW_ORDER.'/'.self::DATA_DUMP_FILE_FETCH_PRODUCTS
                        );

                        return $file->getReturn();
                    }
                );

                $module->shouldReceive('newOrder')->andReturnUsing(
                    function ($got) use ($sk_order_id) {
                        return $sk_order_id;
                    }
                );

                $module->shouldReceive('attachPaymentIdsToOrder')->andReturnUsing(
                    function ($got) use ($sk_payment_id, $sk_order_id) {
                        $payment_id_array = $got[0];
                        $payment_ids = $payment_id_array['payment_ids'];
                        $order_id = $got[1];

                        $this->assertCount(
                            1,
                            $payment_ids,
                            'Check attach has one payment'
                        );
                        $this->assertEquals(
                            $sk_payment_id,
                            $payment_ids[0],
                            'Check attach payment id'
                        );
                        $this->assertEquals(
                            $sk_order_id,
                            $order_id,
                            'Check attach order id'
                        );

                        return $sk_order_id;
                    }
                );

                $module->shouldReceive('findShopCustomerBySubuserEmail')->andReturnUsing(
                    function ($got) use ($sk_customer_id) {
                        return ['id' => $sk_customer_id];
                    }
                );

                $module->shouldReceive('getOrder')->andReturnUsing(
                    function ($got) use ($sk_order_id, &$getOrderStatus) {
                        return [
                            'id' => $sk_order_id,
                            'status' => $getOrderStatus,
                            'is_paid' => false,
                            'order_items' => [],
                        ];
                    }
                );

                $module->shouldReceive('updateOrderStatus')->andReturnUsing(
                    function ($got) use ($new_order, $new_order_id, &$updateStatusCount, &$getOrderStatus) {
                        $update = $got[0];
                        $wanted_status = OrderExport::convertWooCommerceToStorekeeperOrderStatus(
                            $new_order->get_status(self::GET_CONTEXT)
                        );
                        $order_id = get_post_meta($new_order_id, 'storekeeper_id', true);

                        $getOrderStatus = $update['status'];
                        $this->assertNotEquals(
                            $wanted_status,
                            $update['status'],
                            'Status update status difference'
                        );
                        $this->assertEquals(
                            $order_id,
                            $got[1],
                            'Status update order id check'
                        );

                        ++$updateStatusCount;
                    }
                );

                $module->shouldReceive('updateOrder')->andReturnUsing(
                    function () {
                        return null;
                    }
                );
            }
        );

        // Creating an order normally creates a task, while testign we need to trigger it manually
        $OrderHandler->create($new_order_id);

        // Create payment
        $baseGateway = new StoreKeeperBaseGateway(
            "sk_pay_id_{$sk_provider_method_id}",
            "Method title $sk_provider_method_id",
            (int) $sk_provider_method_id,
            ''
        );
        $data = $baseGateway->process_payment($new_order_id);

        // Creating a payment normally creates a task, while testign we need to trigger it manually
        $OrderHandler->create($new_order_id);

        $this->assertErrorReportTask();

        $this->assertEquals(
            'success',
            $data['result'],
            'Payment successfully created'
        );
        $this->assertEquals(
            StoreKeeperBaseGateway::ORDER_STATUS_PENDING,
            $new_order->get_status(self::GET_CONTEXT),
            'Check unpaid order status'
        );
        $this->assertFalse(
            $new_order->is_paid(),
            'Order is unpaid'
        );

        // Sync paid payment
        $paymentGateway = new PaymentGateway();
        $paymentGateway->checkPayment($new_order->get_id());

        // Syncing a payment normally creates a task, while testing we need to trigger it manually
        $OrderHandler->create($new_order_id);

        // update the order object
        $new_order = wc_get_order($new_order_id);

        $this->assertEquals(
            StoreKeeperBaseGateway::ORDER_STATUS_PROCESSING,
            $new_order->get_status(self::GET_CONTEXT),
            'Check paid order status'
        );
        $this->assertTrue(
            $new_order->is_paid(),
            'order is paid'
        );

        // Export tasks
        $this->processAllTasks();

        $this->assertEquals(
            $sk_order_id,
            get_post_meta($new_order_id, 'storekeeper_id', true),
            'storekeeper_id is assigned'
        );
        $this->assertEquals(
            1,
            $updateStatusCount,
            'Order status should only be updated once during this flow'
        );
        $this->assertTrue(
            PaymentModel::orderHasPayment($new_order_id),
            'Order has payment'
        );
        $this->assertTrue(
            PaymentModel::isAllPaymentInSync($new_order_id),
            'Check if the payment was synced'
        );
    }

    public function testOrderWooCommercePayment()
    {
        $this->initApiConnection();

        // init check
        $this->emptyEnvironment();

        // Make an order
        $new_order_id = $this->createWooCommerceOrder();
        $new_order = wc_get_order($new_order_id);

        // Constants
        $OrderHandler = new OrderHandler();
        $skPaymentId = rand();
        $sk_order_id = rand();
        $sk_customer_id = rand();
        $getOrderStatus = OrderExport::STATUS_NEW;

        $updateStatusCount = 0;

        // Setup calls
        StoreKeeperApi::$mockAdapter->withModule(
            'PaymentModule',
            function (MockInterface $module) use ($skPaymentId) {
                $module->shouldReceive('newWebPayment')->andReturnUsing(
                    function ($got) use ($skPaymentId) {
                        // return the only used data
                        return $skPaymentId;
                    }
                );
            }
        );

        StoreKeeperApi::$mockAdapter->withModule(
            'ShopModule',
            function (MockInterface $module) use (
                $new_order,
                $sk_order_id,
                $sk_customer_id,
                $new_order_id,
                &$updateStatusCount,
                &$getOrderStatus
            ) {
                $module->shouldReceive('naturalSearchShopFlatProductForHooks')->andReturnUsing(
                    function ($got) {
                        $file = $this->getDataDump(
                            self::DATA_DUMP_FOLDER_NEW_ORDER.'/'.self::DATA_DUMP_FILE_FETCH_PRODUCTS
                        );

                        return $file->getReturn();
                    }
                );

                $module->shouldReceive('newOrder')->andReturnUsing(
                    function ($got) use ($sk_order_id) {
                        return $sk_order_id;
                    }
                );

                $module->shouldReceive('attachPaymentIdsToOrder')->andReturnUsing(
                    function ($got) {
                        return [];
                    }
                );

                $module->shouldReceive('findShopCustomerBySubuserEmail')->andReturnUsing(
                    function ($got) use ($sk_customer_id) {
                        return ['id' => $sk_customer_id];
                    }
                );

                $module->shouldReceive('getOrder')->andReturnUsing(
                    function ($got) use ($sk_order_id, &$getOrderStatus) {
                        return [
                            'id' => $sk_order_id,
                            'status' => $getOrderStatus,
                            'is_paid' => false,
                            'order_items' => [],
                        ];
                    }
                );

                $module->shouldReceive('updateOrderStatus')->andReturnUsing(
                    function ($got) use ($new_order, $new_order_id, &$updateStatusCount, &$getOrderStatus) {
                        $update = $got[0];
                        $wanted_status = OrderExport::convertWooCommerceToStorekeeperOrderStatus(
                            $new_order->get_status(self::GET_CONTEXT)
                        );
                        $order_id = get_post_meta($new_order_id, 'storekeeper_id', true);

                        $getOrderStatus = $update['status'];
                        $this->assertNotEquals(
                            $wanted_status,
                            $update['status'],
                            'Status update status difference'
                        );
                        $this->assertEquals(
                            $order_id,
                            $got[1],
                            'Status update order id check'
                        );

                        ++$updateStatusCount;
                    }
                );

                $module->shouldReceive('updateOrder')->andReturnUsing(
                    function () {
                        return null;
                    }
                );
            }
        );

        // Creating an order normally creates a task, while testign we need to trigger it manually
        $OrderHandler->create($new_order_id);

        // Create payment
        $baseGateway = new \WC_Gateway_COD();
        $data = $baseGateway->process_payment($new_order_id);

        // update the order object
        $new_order = wc_get_order($new_order_id);

        // Creating a payment normally creates a task, while testign we need to trigger it manually
        $OrderHandler->create($new_order_id);

        $this->assertEquals(
            'success',
            $data['result'],
            'Payment successfully created'
        );
        $this->assertEquals(
            StoreKeeperBaseGateway::ORDER_STATUS_PROCESSING,
            $new_order->get_status(self::GET_CONTEXT),
            'Check unpaid order status'
        );
        $this->assertTrue(
            $new_order->is_paid(),
            'Order is paid'
        );

        // Export tasks
        $this->processAllTasks();

        $this->assertEquals(
            $sk_order_id,
            get_post_meta($new_order_id, 'storekeeper_id', true),
            'storekeeper_id is assigned'
        );
        $this->assertEquals(
            1,
            $updateStatusCount,
            'Order status should only be updated once during this flow'
        );
    }

    private function assertRelationDataSnapshot(\WC_Order $order, array $snapshotData)
    {
        $snapshot = new Dot($snapshotData);

        $shippingPart = $order->has_shipping_address() ? 'shipping' : 'billing';

        // Get the billing name function
        $billingCompanyName = 'get_billing_company';
        $billingNameFunction = empty($order->$billingCompanyName()) ?
            'get_formatted_billing_full_name' :
            $billingCompanyName;

        // Get the shipping name function
        $shippingCompanyName = "get_{$shippingPart}_company";
        $shippingNameFunction = empty($order->$shippingCompanyName()) ?
            "get_formatted_{$shippingPart}_full_name" :
            $shippingCompanyName;

        $testMap = [
            'name' => $billingNameFunction,

            'business_data.name' => 'get_billing_company',
            'business_data.country_iso2' => 'get_billing_country',

            'contact_person.familyname' => 'get_billing_last_name',
            'contact_person.firstname' => 'get_billing_first_name',
            'contact_person.contact_set.email' => 'get_billing_email',
            'contact_person.contact_set.phone' => 'get_billing_phone',
            'contact_person.contact_set.name' => 'get_formatted_billing_full_name',

            'contact_set.email' => 'get_billing_email',
            'contact_set.phone' => 'get_billing_phone',
            'contact_set.name' => $billingNameFunction,

            'address_billing.state' => 'get_billing_state',
            'address_billing.city' => 'get_billing_city',
            'address_billing.zipcode' => 'get_billing_postcode',
            'address_billing.street' => 'billing:street',
            'address_billing.country_iso2' => 'get_billing_country',
            'address_billing.name' => $billingNameFunction,

            'address_billing.contact_set.email' => 'get_billing_email',
            'address_billing.contact_set.phone' => 'get_billing_phone',
            'address_billing.contact_set.name' => $billingNameFunction,

            'contact_address.state' => "get_{$shippingPart}_state",
            'contact_address.city' => "get_{$shippingPart}_city",
            'contact_address.zipcode' => "get_{$shippingPart}_postcode",
            'contact_address.street' => "$shippingPart:street",
            'contact_address.country_iso2' => "get_{$shippingPart}_country",
            'contact_address.name' => $shippingNameFunction,

            'contact_address.contact_set.email' => "get_{$shippingPart}_email",
            'contact_address.contact_set.phone' => "get_{$shippingPart}_phone",
            'contact_address.contact_set.name' => $shippingNameFunction,
        ];

        foreach ($testMap as $snapshotPath => $orderFunction) {
            if (strpos($orderFunction, ':')) {
                $addressName = explode(':', $orderFunction)[0];

                $function1 = "get_{$addressName}_address_1";
                $function2 = "get_{$addressName}_address_2";

                $street1 = trim($order->$function1('edit'));
                $street2 = trim($order->$function2('edit'));
                $orderValue = trim("$street1 $street2");
            } elseif (stripos($orderFunction, 'full_name')) {
                $orderValue = $order->$orderFunction();
            } else {
                $orderValue = $order->$orderFunction('edit');
            }
            $snapshotValue = $snapshot->get($snapshotPath);
            $this->assertEquals($orderValue, $snapshotValue, "Testing relation snapshot: $snapshotPath");
        }
    }

    /**
     * @param bool $order_paid
     */
    public function createPaymentForMethodId(int $sk_provider_method_id, \WC_Order $order, string $message): void
    {
        $gateway = new StoreKeeperBaseGateway(
            "sk_pay_id_{$sk_provider_method_id}",
            "Method title $sk_provider_method_id",
            (int) $sk_provider_method_id,
            ''
        );
        $data = $gateway->process_payment($order->get_id());
        $error = $gateway->getLastError();

        if (!empty($error)) {
            throw $error;
        }
        // assert payment
        $this->assertEquals('success', $data['result'], $message.': payment created succesfully');
        $this->assertNotEmpty($data['redirect'], $message.': redirect url was crated');
    }

    public function assertErrorReportTask()
    {
        $task = TaskHandler::getScheduledTask(TaskHandler::REPORT_ERROR);
        $taskMetaData = $task['meta_data'] ?? [];
        if (array_key_exists('exception-message', $taskMetaData)) {
            $message = $taskMetaData['exception-message'];
            throw new Exception("Report error task found with message: $message");
        }
    }
}
