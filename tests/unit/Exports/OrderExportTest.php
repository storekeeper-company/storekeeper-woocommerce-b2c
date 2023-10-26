<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Exports;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Mockery\MockInterface;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks\InfoHandler;
use StoreKeeper\WooCommerce\B2C\Exceptions\OrderDifferenceException;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\OrderHandler;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Tools\StringFunctions;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use WC_Helper_Order;

class OrderExportTest extends AbstractOrderExportTest
{
    use ArraySubsetAsserts;
    const DATA_DUMP_FOLDER_CREATE = 'exports/orderExports/newOrder';

    const GET_CONTEXT = 'edit';

    /**
     * @param $new_order_id
     * @param $sk_customer_id
     * @param $new_order
     * @param $sk_order
     */
    public function assertNewOrder($new_order_id, $sk_customer_id, $new_order, $sk_order)
    {
        $wc_order = WC()->order_factory->get_order($new_order_id);
        $expected = [
            'billing_address__merge' => false,
            'shipping_address__merge' => false,
            'force_order_if_product_not_active' => true,
            'force_backorder_if_not_on_stock' => true,
            'is_anonymous' => false,
            'relation_data_id' => $sk_customer_id,
            'shop_order_number' => $new_order_id,
            'customer_comment' => $new_order['customer_note'],
            'customer_reference' => get_bloginfo('name'),
        ];
        foreach ($expected as $key => $value) {
            $this->assertEquals($value, $sk_order[$key], 'New order: '.$key);
        }

        /** @var \WC_Order_Item_Product $actual_order_item */
        $actual_order_item = current($wc_order->get_items());
        $product_id = $actual_order_item->get_variation_id() > 0
            ? $actual_order_item->get_variation_id() : $actual_order_item->get_product_id();
        $order_item = $sk_order['order_items'][0];
        $this->assertEquals('DUMMY SKU', $order_item['sku'], 'sku');
        $this->assertEquals(7.5, $order_item['ppu_wt'], 'ppu_wt');
        $this->assertEquals(4, $order_item['quantity'], 'quantity');
        $this->assertEquals(
            get_post_meta($product_id, 'storekeeper_id', true),
            0 !== $order_item['shop_product_id'] ? $order_item['shop_product_id'] : '',
            'Storekeeper id does not match'
        );

        $emballage = null;
        if (isset($sk_order['order_items'][1]['is_shipping'])) {
            $shipping = $sk_order['order_items'][1];
        } else {
            $emballage = $sk_order['order_items'][1];
            $shipping = $sk_order['order_items'][2];
        }

        $this->assertEquals('flat rate shipping', $shipping['sku'], 'sku');
        $this->assertEquals(1, $shipping['quantity'], 'quantity');
        $this->assertEquals(10, $shipping['ppu_wt'], 'ppu_wt');

        if ($emballage) {
            $emballageTaxRate = $emballage['tax_rate_id'] ?? null;
            $this->assertEquals(9, $emballageTaxRate, 'Emballage tax rate ID does not match');
        }

        $expectedBillingStreet = $new_order['billing_address_1'].' '.$new_order['billing_address_2'];

        $expect_billing = [
            'name' => $new_order['billing_first_name'].' '.$new_order['billing_last_name'],
            'isprivate' => empty($new_order['billing_company']),
            'address_billing' => [
                    'state' => $new_order['billing_state'],
                    'city' => $new_order['billing_city'],
                    'zipcode' => $new_order['billing_postcode'],
                    'street' => $expectedBillingStreet,
                    'country_iso2' => $new_order['billing_country'],
                    'name' => $new_order['billing_first_name'].' '.$new_order['billing_last_name'],
                ],
            'contact_set' => [
                    'email' => $new_order['billing_email'],
                    'phone' => $new_order['billing_phone'],
                    'name' => $new_order['billing_first_name'].' '.$new_order['billing_last_name'],
                ],
            'contact_person' => [
                    'firstname' => $new_order['billing_first_name'],
                    'familyname' => $new_order['billing_last_name'],
                ],
        ];

        if ('NL' === $wc_order->get_billing_country()) {
            $splitStreet = OrderExport::splitStreetNumber($new_order['billing_address_house_number']);
            $expect_billing['address_billing']['streetnumber'] = $splitStreet['streetnumber'];
            $expect_billing['address_billing']['flatnumber'] = $splitStreet['flatnumber'];
        }
        if (!empty($new_order['billing_company'])) {
            $expect_billing['business_data'] = [
                'name' => $new_order['billing_company'],
                'country_iso2' => $new_order['billing_country'],
            ];
        }

        $expect_shipping = $expect_billing;
        if ($wc_order->has_shipping_address()) {
            $expectedShippingStreet = $new_order['shipping_address_1'].' '.$new_order['shipping_address_2'];

            $expect_shipping = [
                'name' => $new_order['shipping_first_name'].' '.$new_order['shipping_last_name'],
                'isprivate' => empty($new_order['shipping_company']),
                'contact_address' => [
                        'state' => $new_order['shipping_state'],
                        'city' => $new_order['shipping_city'],
                        'zipcode' => $new_order['shipping_postcode'],
                        'street' => $expectedShippingStreet,
                        'country_iso2' => $new_order['shipping_country'],
                        'name' => $new_order['shipping_first_name'].' '.$new_order['shipping_last_name'],
                    ],
                'contact_set' => [
                        'email' => $new_order['billing_email'],
                        'phone' => $new_order['billing_phone'],
                        'name' => $new_order['shipping_first_name'].' '.$new_order['shipping_last_name'],
                    ],
                'contact_person' => [
                        'firstname' => $new_order['shipping_first_name'],
                        'familyname' => $new_order['shipping_last_name'],
                    ],
            ];

            if ('NL' === $wc_order->get_shipping_country()) {
                $splitStreet = OrderExport::splitStreetNumber($new_order['shipping_address_house_number']);
                $expect_shipping['contact_address']['streetnumber'] = $splitStreet['streetnumber'];
                $expect_shipping['contact_address']['flatnumber'] = $splitStreet['flatnumber'];
            }
        }

        if (!empty($new_order['shipping_company'])) {
            $expect_shipping['business_data'] = [
                'name' => $new_order['shipping_company'],
                'country_iso2' => $new_order['shipping_country'],
            ];
        }

        $this->assertDeepArray($expect_billing, $sk_order['billing_address'], 'billing_address ');

        $this->assertDeepArray($expect_shipping, $sk_order['shipping_address'], 'shipping_address ');

        $coupons = $wc_order->get_coupons() ?? [];
        $skCoupons = $sk_order['order_coupon_codes'] ?? [];

        $this->assertEquals(
            count($coupons),
            count($skCoupons),
            'Missing coupon codes'
        );

        if (count($coupons) > 0) {
            $skCouponMap = [];
            foreach ($skCoupons as $skCoupon) {
                $skCouponMap[$skCoupon['code']] = $skCoupon['value_wt'];
            }

            foreach ($coupons as $couponId => $coupon) {
                $code = $coupon->get_code();
                $this->assertArrayHasKey($code, $skCouponMap, 'Coupon code not exported');
                $this->assertEquals(
                    $skCouponMap[$code],
                    wc_get_order_item_meta($couponId, 'discount_amount', true),
                    'Incorrect discount amount'
                );
            }
        }
    }

    public function dataProviderOrderDifference()
    {
        $tests = [];

        $tests['same items with backoffice'] = [
            [
                [
                    'name' => 'EXISTING DUMMY',
                    'sku' => 'EXISTING DUMMY SKU',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                    'storekeeper_id' => 1,
                ],
                [
                    'name' => 'EXISTING DUMMY 2',
                    'sku' => 'EXISTING DUMMY SKU 2',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                    'storekeeper_id' => 2,
                ],
            ],
            [
                [
                    'name' => 'EXISTING DUMMY',
                    'sku' => 'EXISTING DUMMY SKU',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                ],
                [
                    'name' => 'EXISTING DUMMY 2',
                    'sku' => 'EXISTING DUMMY SKU 2',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                ],
            ],
            false,
        ];

        $tests['same items with backoffice with decimals'] = [
            [
                [
                    'name' => 'EXISTING DUMMY',
                    'sku' => 'EXISTING DUMMY SKU',
                    'quantity' => 1,
                    'ppu_wt' => 100.25,
                    'storekeeper_id' => 1,
                ],
                [
                    'name' => 'EXISTING DUMMY 2',
                    'sku' => 'EXISTING DUMMY SKU 2',
                    'quantity' => 1,
                    'ppu_wt' => 100.15,
                    'storekeeper_id' => 2,
                ],
            ],
            [
                [
                    'name' => 'EXISTING DUMMY',
                    'sku' => 'EXISTING DUMMY SKU',
                    'quantity' => 1,
                    'ppu_wt' => 100.25,
                ],
                [
                    'name' => 'EXISTING DUMMY 2',
                    'sku' => 'EXISTING DUMMY SKU 2',
                    'quantity' => 1,
                    'ppu_wt' => 100.15,
                ],
            ],
            false,
        ];

        $tests['missing item from backoffice order'] = [
            [
                [
                    'name' => 'EXISTING DUMMY',
                    'sku' => 'EXISTING DUMMY SKU',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                    'storekeeper_id' => 1,
                ],
                [
                    'name' => 'NON EXISTING DUMMY',
                    'sku' => 'NON-EXISTING DUMMY SKU',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                    'storekeeper_id' => 2,
                ],
            ],
            [
                [
                    'name' => 'EXISTING DUMMY',
                    'sku' => 'EXISTING DUMMY SKU',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                ],
            ],
            true,
        ];

        $tests['same items from backoffice order but different quantity'] = [
            [
                [
                    'name' => 'NON EXISTING DUMMY',
                    'sku' => 'EXISTING DUMMY SKU',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                    'storekeeper_id' => 1,
                ],
                [
                    'name' => 'NON EXISTING DUMMY 2',
                    'sku' => 'EXISTING DUMMY SKU 2',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                    'storekeeper_id' => 2,
                ],
            ],
            [
                [
                    'name' => 'EXISTING DUMMY',
                    'sku' => 'EXISTING DUMMY SKU',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                ],
                [
                    'name' => 'EXISTING DUMMY 2',
                    'sku' => 'EXISTING DUMMY SKU 2',
                    'quantity' => 2,
                    'ppu_wt' => 100,
                ],
            ],
            true,
        ];

        $tests['same items from backoffice order but different price per unit'] = [
            [
                [
                    'name' => 'EXISTING DUMMY',
                    'sku' => 'EXISTING DUMMY SKU',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                    'storekeeper_id' => 1,
                ],
                [
                    'name' => 'EXISTING DUMMY 2',
                    'sku' => 'EXISTING DUMMY SKU 2',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                    'storekeeper_id' => 2,
                ],
            ],
            [
                [
                    'name' => 'EXISTING DUMMY',
                    'sku' => 'EXISTING DUMMY SKU',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                ],
                [
                    'name' => 'EXISTING DUMMY 2',
                    'sku' => 'EXISTING DUMMY SKU 2',
                    'quantity' => 1,
                    'ppu_wt' => 200,
                ],
            ],
            true,
        ];

        $tests['same items from backoffice order but 2 products with same SKU'] = [
            [
                [
                    'name' => 'EXISTING DUMMY',
                    'sku' => 'EXISTING DUMMY SKU',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                    'storekeeper_id' => 1,
                ],
                [
                    'name' => 'EXISTING DUMMY 2',
                    'sku' => 'EXISTING DUMMY SKU 2',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                    'storekeeper_id' => 12,
                ],
            ],
            [
                [
                    'name' => 'EXISTING DUMMY',
                    'sku' => 'EXISTING DUMMY SKU',
                    'quantity' => 1,
                    'ppu_wt' => 100,
                ],
                [
                    'name' => 'EXISTING DUMMY 2',
                    'sku' => 'EXISTING DUMMY SKU 2',
                    'quantity' => 1,
                    'ppu_wt' => 200,
                ],
                [
                    'name' => 'EXISTING DUMMY 3',
                    'sku' => 'EXISTING DUMMY SKU',
                    'quantity' => 2,
                    'ppu_wt' => 100,
                ],
            ],
            true,
        ];

        return $tests;
    }

    /**
     * @dataProvider dataProviderOrderDifference
     */
    public function testOrderDifferenceBySet($actual, $expected, $result)
    {
        $this->initApiConnection();

        $exportTask = new OrderExport([]);

        $order = wc_create_order();

        $shopProductMap = [];
        foreach ($actual as $orderItem) {
            $orderProduct = $this->createOrderProduct($orderItem);
            $order->add_product($orderProduct);
            $shopProductMap[$orderProduct->get_id()] = $orderItem['storekeeper_id'];
        }
        $order->update_meta_data(OrderHandler::SHOP_PRODUCT_ID_MAP, $shopProductMap);
        $order->calculate_totals();
        $order->save();
        $hasDifference = false;
        try {
            $exportTask->checkOrderDifference(
                new \WC_Order($order->get_id()),
                [
                    'order_items' => $expected,
                    'value_wt' => $this->computeOrderTotal($expected),
                ]
            );
        } catch (OrderDifferenceException $exception) {
            $hasDifference = true;
        }

        $this->assertSame($result, $hasDifference, 'Difference result is not same as expected');
    }

    protected function computeOrderTotal(array $orderItems)
    {
        $total = 0;
        foreach ($orderItems as $orderItem) {
            $quantity = $orderItem['quantity'];
            $pricePerUnit = $orderItem['ppu_wt'];
            $subTotal = $quantity * $pricePerUnit;
            $total += $subTotal;
        }

        return round($total, 2);
    }

    protected function createOrderProduct(array $args): \WC_Product
    {
        if (0 !== wc_get_product_id_by_sku($args['sku'])) {
            $orderProduct = new \WC_Product(wc_get_product_id_by_sku($args['sku']));
        } else {
            $orderProduct = new \WC_Product();
            $orderProduct->set_name($args['sku']);
            $orderProduct->set_price($args['ppu_wt']);
            $orderProduct->set_sku($args['sku']);
            $orderProduct->set_props([
                'storekeeper_id' => $args['storekeeper_id'],
            ]);
            $orderProduct->save();
        }

        return $orderProduct;
    }

    public function testOrderCreate()
    {
        $this->initApiConnection();

        $this->mockApiCallsFromDirectory(self::DATA_DUMP_FOLDER_CREATE, true);

        $this->emptyEnvironment();

        $new_order = $this->getOrderProps();
        $new_order_id = $this->createWooCommerceOrder($new_order);
        $this->processNewOrder($new_order_id, $new_order);
    }

    public function dataProviderOrderCreateWithNlCountry()
    {
        $tests = [];

        $tests['with street number and flat number'] = [
            'houseNumber' => '146A02B',
            'expectedStreetNumber' => '146',
            'expectedFlatNumber' => 'A02B',
        ];

        $tests['with street number only'] = [
            'houseNumber' => '1011',
            'expectedStreetNumber' => '1011',
            'expectedFlatNumber' => '',
        ];

        return $tests;
    }

    /**
     * @dataProvider dataProviderOrderCreateWithNlCountry
     */
    public function testOrderCreateWithNlCountry(string $houseNumber, string $expectedStreetNumber, string $expectedFlatNumber)
    {
        $this->initApiConnection();

        $this->mockApiCallsFromDirectory(self::DATA_DUMP_FOLDER_CREATE);

        $this->emptyEnvironment();

        $newOrderWithNlCountry = $this->getOrderProps(true, $houseNumber);
        $newOrderWithNlCountryId = $this->createWooCommerceOrder($newOrderWithNlCountry);
        $woocommerceOrder = new \WC_Order($newOrderWithNlCountryId);
        $woocommerceOrder->update_meta_data('billing_address_house_number', $newOrderWithNlCountry['billing_address_house_number']);
        $woocommerceOrder->update_meta_data('shipping_address_house_number', $newOrderWithNlCountry['shipping_address_house_number']);
        $woocommerceOrder->save();

        // this is normally created when the woocommerce_checkout_order_processed hook is fired
        // StoreKeeper\WooCommerce\B2C\Core::setOrderHooks
        $orderHandler = new OrderHandler();
        $task = $orderHandler->create($newOrderWithNlCountryId);

        // set the checker for expected result
        $skOrderId = mt_rand();
        $skCustomerId = mt_rand();

        $sentOrders = [];
        StoreKeeperApi::$mockAdapter->withModule(
            'ShopModule',
            function (MockInterface $module) use ($skCustomerId, $skOrderId, $newOrderWithNlCountry, $newOrderWithNlCountryId, &$sentOrders, $expectedStreetNumber, $expectedFlatNumber) {
                $module->expects('newOrder')
                    ->andReturnUsing(
                        function ($got) use ($newOrderWithNlCountryId, $skCustomerId, $newOrderWithNlCountry, $skOrderId, &$sentOrders) {
                            [$order] = $got;

                            $this->assertNewOrder($newOrderWithNlCountryId, $skCustomerId, $newOrderWithNlCountry, $order);

                            $sentOrders = $order;
                            $sentOrders['id'] = $skOrderId;
                            $sentOrders = $this->calculateNewOrder($sentOrders);

                            return $skOrderId;
                        }
                    );

                $module->expects('findShopCustomerBySubuserEmail')
                    ->andReturnUsing(
                        function () {
                            throw new GeneralException('Not found', 0);
                        }
                    );
                $module->expects('getOrder')
                    ->andReturnUsing(
                        function ($got) use (&$sentOrders, $skOrderId) {
                            $this->assertEquals($skOrderId, $got[0]);

                            return $sentOrders;
                        }
                    );

                $module->expects('newShopCustomer')
                    ->andReturnUsing(function ($got) use ($expectedStreetNumber, $expectedFlatNumber, $skCustomerId) {
                        $data = $got[0];
                        $shippingAddress = $data['relation']['contact_address'];
                        $billingAddress = $data['relation']['address_billing'];

                        $this->assertEquals($expectedStreetNumber, $shippingAddress['streetnumber'], 'Expected shipping address street number does not match');
                        $this->assertEquals($expectedFlatNumber, $shippingAddress['flatnumber'], 'Expected shipping address flat number does not match');

                        $this->assertEquals($expectedStreetNumber, $billingAddress['streetnumber'], 'Expected billing address street number does not match');
                        $this->assertEquals($expectedFlatNumber, $billingAddress['flatnumber'], 'Expected billing address flat number does not match');

                        return $skCustomerId;
                    });

                /*
                 * Unrelated-calls for this test
                 */
                $module->expects('updateOrder')->andReturnUsing(
                    function () {
                        return null;
                    }
                );
            }
        );

        // run the sync
        $this->processTask($task);

        $this->assertEquals(
            $skOrderId,
            get_post_meta($newOrderWithNlCountryId, 'storekeeper_id', true),
            'storekeeper_id is assigned on wordpress order'
        );
    }

    public function testOrderCreateWithEmballage()
    {
        $this->initApiConnection();

        $this->mockApiCallsFromDirectory(self::DATA_DUMP_FOLDER_CREATE, true);

        $this->emptyEnvironment();

        $newOrder = $this->getOrderProps();
        $newOrderId = $this->createWooCommerceOrder($newOrder);
        $woocommerceOrder = new \WC_Order($newOrderId);

        $emballageFee = new \WC_Order_Item_Fee();
        $emballageFee->set_name('Emballage fee');
        $emballageFee->set_amount(65.00);
        $emballageFee->set_total(65.00);
        $emballageFee->update_meta_data(OrderExport::EMBALLAGE_TAX_RATE_ID_META_KEY, 9);

        $woocommerceOrder->add_item($emballageFee);
        $woocommerceOrder->save();
        $this->processNewOrder($newOrderId, $newOrder);
    }

    public function testCancelledOrder()
    {
        $this->initApiConnection();
        $this->emptyEnvironment();

        $newOrderTimesCalled = 0;
        $orderHandler = new OrderHandler();

        $syncedCancelledOrder = WC_Helper_Order::create_order();
        $syncedCancelledOrder->update_status(OrderExport::STATUS_CANCELLED);
        $syncedCancelledOrder->save();

        $newToCancelledOrder = WC_Helper_Order::create_order();
        $newToCancelledOrder->save();

        $unsyncedCancelledOrder = WC_Helper_Order::create_order();
        $unsyncedCancelledOrder->update_status(OrderExport::STATUS_CANCELLED);
        $unsyncedCancelledOrder->save();

        $unsyncedCancelledOrderTaskIds = TaskModel::getTasksByStoreKeeperId($unsyncedCancelledOrder->get_id());
        // Simulate marking tasks as success
        foreach ($unsyncedCancelledOrderTaskIds as $taskId) {
            TaskModel::update($taskId, ['status' => TaskHandler::STATUS_SUCCESS]);
        }

        $sentOrder = [];
        StoreKeeperApi::$mockAdapter
            ->withModule(
                'ShopModule',
                function (MockInterface $module) use (&$sentOrder, $syncedCancelledOrder, $unsyncedCancelledOrder, &$newOrderTimesCalled) {
                    $module->allows('findShopCustomerBySubuserEmail')->andReturnUsing(
                        function () {
                            return ['id' => mt_rand()];
                        }
                    );

                    $module->allows('naturalSearchShopFlatProductForHooks')->andReturnUsing(
                        function () {
                            return [
                                'data' => [],
                                'total' => 0,
                                'count' => 0,
                            ];
                        }
                    );

                    $module->allows('newOrder')->andReturnUsing(
                        function ($params) use (&$sentOrder, $syncedCancelledOrder, $unsyncedCancelledOrder, &$newOrderTimesCalled) {
                            [$order] = $params;
                            if ($order['shop_order_number'] === $unsyncedCancelledOrder->get_id()) {
                                throw new \Exception('Should not be synchronized');
                            }

                            $sentOrder = $order;
                            $sentOrder['id'] = rand();
                            $sentOrder = $this->calculateNewOrder($sentOrder);

                            if ($order['shop_order_number'] === $syncedCancelledOrder->get_id()) {
                                $sentOrder['status'] = OrderExport::STATUS_CANCELLED;
                            }

                            ++$newOrderTimesCalled;

                            return $sentOrder['id'];
                        }
                    );

                    $module->allows('getOrder')->andReturnUsing(
                        function () use (&$sentOrder) {
                            return $sentOrder;
                        }
                    );

                    $module->allows('updateOrder')->andReturnUsing(
                        function () {
                            return null;
                        }
                    );

                    $module->allows('updateOrderStatus')->andReturnUsing(
                        function ($got) {
                            return null;
                        }
                    );
                }
            );

        $syncedCancelledOrderTask = $orderHandler->create($syncedCancelledOrder->get_id());
        $this->processTask($syncedCancelledOrderTask);

        $newToCancelledOrderCreateTask = $orderHandler->create($newToCancelledOrder->get_id());
        $this->processTask($newToCancelledOrderCreateTask);

        $newToCancelledOrder->set_status(OrderExport::STATUS_CANCELLED);
        $newToCancelledOrder->save();
        $newToCancelledOrderUpdateTask = $orderHandler->updateWithIgnore($newToCancelledOrder->get_id());
        $this->processTask($newToCancelledOrderUpdateTask);

        $infoHandler = new InfoHandler();
        $webShopInfo = $infoHandler->run();

        $this->assertEquals(2, $newOrderTimesCalled, 'ShopModule::newOrder should only be called twice');
        $this->assertCount(0, $webShopInfo['extra']['system_status']['order']['ids_not_synchronized'], 'There should be no unsynchronized orders');
    }

    public function testWooCommerceOnlyProduct()
    {
        $this->initApiConnection();
        $this->emptyEnvironment();

        $order = WC_Helper_Order::create_order();
        $order->save();

        $sent_order = [];
        StoreKeeperApi::$mockAdapter
            ->withModule(
                'ShopModule',
                function (MockInterface $module) use (&$sent_order) {
                    $module->shouldReceive('findShopCustomerBySubuserEmail')->andReturnUsing(
                        function () {
                            return ['id' => rand()];
                        }
                    );

                    $module->shouldReceive('naturalSearchShopFlatProductForHooks')->andReturnUsing(
                        function () {
                            return [
                                'data' => [],
                                'total' => 0,
                                'count' => 0,
                            ];
                        }
                    );

                    $module->shouldReceive('newOrder')->andReturnUsing(
                        function ($params) use (&$sent_order) {
                            [$order] = $params;

                            $sent_order = $order;
                            $sent_order['id'] = rand();
                            $sent_order = $this->calculateNewOrder($sent_order);

                            return $sent_order['id'];
                        }
                    );

                    $module->shouldReceive('getOrder')->andReturnUsing(
                        function () use (&$sent_order) {
                            return $sent_order;
                        }
                    );

                    $module->shouldReceive('updateOrder')->andReturnUsing(
                        function () {
                            return null;
                        }
                    );
                }
            );

        $OrderHandler = new OrderHandler();
        $task = $OrderHandler->create($order->get_id());

        $this->processTask($task);
    }

    public function testCustomerEmailIsAdmin()
    {
        $this->initApiConnection();
        $this->emptyEnvironment();

        $existingAdminEmail = 'admin@storekeeper.nl';
        $order = WC_Helper_Order::create_order();
        $order->set_billing_email($existingAdminEmail);
        $order->save();

        $sent_order = [];
        StoreKeeperApi::$mockAdapter
            ->withModule(
                'ShopModule',
                function (MockInterface $module) use (&$sent_order, $existingAdminEmail) {
                    $module->allows('findShopCustomerBySubuserEmail')->andReturnUsing(
                        function ($payload) use ($existingAdminEmail) {
                            [$emailPayload] = $payload;
                            $email = $emailPayload['email'];

                            if ($email === $existingAdminEmail) {
                                throw GeneralException::buildFromBody(['class' => 'ShopModule::EmailIsAdminUser']);
                            }

                            // Throwing this error basically means email was not found
                            throw GeneralException::buildFromBody(['error' => 'Wrong DataBasicSubuser']);
                        }
                    );

                    $module->allows('newShopCustomer')->andReturnUsing(
                        function ($payload) use ($existingAdminEmail) {
                            [$relationPayload] = $payload;
                            $subuser = $relationPayload['relation']['subuser'];

                            $this->assertNotEquals($existingAdminEmail, $subuser['login'], 'Subuser login should not be the admin email');
                            $this->assertNotEquals($existingAdminEmail, $subuser['email'], 'Subuser email should not be the admin email');

                            return mt_rand();
                        }
                    );

                    $module->allows('naturalSearchShopFlatProductForHooks')->andReturn([
                        'data' => [],
                        'total' => 0,
                        'count' => 0,
                    ]);

                    $module->allows('newOrder')->andReturnUsing(
                        function ($payload) use (&$sent_order) {
                            [$order] = $payload;

                            $sent_order = $order;
                            $sent_order['id'] = rand();
                            $sent_order = $this->calculateNewOrder($sent_order);

                            return $sent_order['id'];
                        }
                    );

                    $module->allows('getOrder')->andReturnUsing(
                        function () use (&$sent_order) {
                            return $sent_order;
                        }
                    );

                    $module->allows('updateOrder')->andReturnNull();
                }
            );

        $OrderHandler = new OrderHandler();
        $task = $OrderHandler->create($order->get_id());

        $this->processTask($task);
    }

    /**
     * In some cases when the theme is broken the order gets a Variable product instead of variance
     * So far it only happen when the order has single variance.
     *
     * @see https://app.clickup.com/t/861mfzp0z
     */
    public function testVariableProductAutoselect()
    {
        $this->initApiConnection();
        $this->emptyEnvironment();

        // create variable product with single variation and order it
        $product = new \WC_Product_Variable();
        $product->set_props(
            [
                'name' => 'Dummy Variable Product',
                'sku' => 'DUMMY VARIABLE SKU',
            ]
        );

        $attributes = [];

        $attribute = new \WC_Product_Attribute();
        $attribute_data = \WC_Helper_Product::create_attribute('size', ['small', 'large', 'huge']);
        $attribute->set_id($attribute_data['attribute_id']);
        $attribute->set_name($attribute_data['attribute_taxonomy']);
        $attribute->set_options($attribute_data['term_ids']);
        $attribute->set_position(1);
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        $attributes[] = $attribute;

        $product->set_attributes($attributes);
        $product->save();

        $variation_1 = new \WC_Product_Variation();
        $variation_1->set_props(
            [
                'parent_id' => $product->get_id(),
                'sku' => 'DUMMY SKU VARIABLE SMALL',
                'regular_price' => 10,
            ]
        );
        $variation_1->set_attributes(['pa_size' => 'small']);
        $variation_1->save();

        $order = WC_Helper_Order::create_order(1, $product);
        $order->save();

        $sent_order = [];
        StoreKeeperApi::$mockAdapter
            ->withModule(
                'ShopModule',
                function (MockInterface $module) use ($variation_1, $product, &$sent_order) {
                    $module->shouldReceive('findShopCustomerBySubuserEmail')->andReturnUsing(
                        function () {
                            return ['id' => rand()];
                        }
                    );

                    $module->shouldReceive('naturalSearchShopFlatProductForHooks')->andReturnUsing(
                        function () {
                            return [
                                'data' => [],
                                'total' => 0,
                                'count' => 0,
                            ];
                        }
                    );

                    $module->shouldReceive('newOrder')->andReturnUsing(
                        function ($params) use ($variation_1, $product, &$sent_order) {
                            [$order] = $params;

                            $productLine = null;
                            foreach ($order['order_items']  as $item) {
                                if (empty($item['is_shipping'])) {
                                    $productLine = $item;
                                }
                            }
                            $this->assertArraySubset(
                                [
                                    'sku' => $variation_1->get_sku('edit'),
                                    'name' => $product->get_name('edit'),
                                    'extra' => [],
                                ],
                                $productLine,
                                false,
                                'Variance was send to order'
                            );

                            $sent_order = $order;
                            $sent_order['id'] = rand();
                            $sent_order = $this->calculateNewOrder($sent_order);

                            return $sent_order['id'];
                        }
                    );

                    $module->shouldReceive('getOrder')->andReturnUsing(
                        function ($got) use (&$sent_order) {
                            return $sent_order;
                        }
                    );

                    $module->shouldReceive('updateOrder')->andReturnUsing(
                        function () {
                            return null;
                        }
                    );
                }
            );

        $OrderHandler = new OrderHandler();
        $task = $OrderHandler->create($order->get_id());

        $this->processTask($task);
    }

    public function testOrderCreateNoShippingAddress()
    {
        $this->initApiConnection();

        $this->mockApiCallsFromDirectory(self::DATA_DUMP_FOLDER_CREATE, true);

        $this->emptyEnvironment();

        $create_order = []; // Used to create the order
        $new_order = $this->getOrderProps(); // used to assert the order
        foreach ($new_order as $key => $value) {
            // If the key starts with shipping, its being overwritten with its billing counterpart
            if (StringFunctions::startsWith($key, 'shipping_')) {
                preg_match('/shipping_(\S*)/', $key, $matches);
                $new_order[$key] = $new_order['billing_'.$matches[1]];
            } else {
                // if it does not starts with shipping, we add it to the create_order object;
                $create_order[$key] = $value;
            }
        }
        $new_order_id = $this->createWooCommerceOrder($create_order);
        $this->processNewOrder($new_order_id, $new_order);
    }

    /**
     * @throws \Exception
     */
    protected function processNewOrder(int $new_order_id, array $new_order): void
    {
        // this is normally created when the woocommerce_checkout_order_processed hook is fired
        // StoreKeeper\WooCommerce\B2C\Core::setOrderHooks
        $OrderHandler = new OrderHandler();
        $task = $OrderHandler->create($new_order_id);

        // set the checker for expected result
        $sk_order_id = mt_rand();
        $sk_customer_id = mt_rand();

        $sent_order = [];
        StoreKeeperApi::$mockAdapter->withModule(
            'ShopModule',
            function (MockInterface $module) use ($sk_customer_id, $sk_order_id, $new_order, $new_order_id, &$sent_order) {
                $module->expects('newOrder')
                    ->andReturnUsing(
                        function ($got) use ($new_order_id, $sk_customer_id, $new_order, $sk_order_id, &$sent_order) {
                            [$order] = $got;

                            $this->assertNewOrder($new_order_id, $sk_customer_id, $new_order, $order);

                            $sent_order = $order;
                            $sent_order['id'] = $sk_order_id;
                            $sent_order = $this->calculateNewOrder($sent_order);

                            return $sk_order_id;
                        }
                    );

                $module->expects('findShopCustomerBySubuserEmail')
                    ->andReturnUsing(
                        function ($got) use ($sk_customer_id, $new_order) {
                            $this->assertEquals($new_order['billing_email'], $got[0]['email']);

                            return [
                                'id' => $sk_customer_id,
                                // only this field is used
                            ];
                        }
                    );
                $module->expects('getOrder')
                    ->andReturnUsing(
                        function ($got) use (&$sent_order, $sk_order_id) {
                            $this->assertEquals($sk_order_id, $got[0]);

                            return $sent_order;
                        }
                    );

                /*
                 * Unrelated-calls for this test
                 */
                $module->expects('updateOrder')->andReturnUsing(
                    function () {
                        return null;
                    }
                );
            }
        );

        // run the sync
        $this->processTask($task);

        $this->assertEquals(
            $sk_order_id,
            get_post_meta($new_order_id, 'storekeeper_id', true),
            'storekeeper_id is assigned on wordpress order'
        );
    }

    public function dataProviderStreetNumber()
    {
        $streetNumbers = [];
        $streetNumbers['160'] = [
            'streetnumber' => '160',
            'flatnumber' => '',
        ];
        $streetNumbers['23'] = [
            'streetnumber' => '23',
            'flatnumber' => '',
        ];
        $streetNumbers['23-9'] = [
            'streetnumber' => '23',
            'flatnumber' => '9',
        ];
        $streetNumbers['23-9-A'] = [
            'streetnumber' => '23',
            'flatnumber' => '9-A',
        ];
        $streetNumbers['146A02'] = [
            'streetnumber' => '146',
            'flatnumber' => 'A02',
        ];
        $streetNumbers['146A02B'] = [
            'streetnumber' => '146',
            'flatnumber' => 'A02B',
        ];
        $streetNumbers['20hs'] = [
            'streetnumber' => '20',
            'flatnumber' => 'hs',
        ];
        $streetNumbers['1011'] = [
            'streetnumber' => '1011',
            'flatnumber' => '',
        ];
        $streetNumbers[' 146 A02B'] = [
            'streetnumber' => '146',
            'flatnumber' => 'A02B',
        ];
        $streetNumbers['146/01'] = [
            'streetnumber' => '146',
            'flatnumber' => '01',
        ];
        $streetNumbers['146 01'] = [
            'streetnumber' => '146',
            'flatnumber' => '01',
        ];

        $entries = [];
        foreach ($streetNumbers as $streetNumber => $expect) {
            $entries['Street number: '.$streetNumber] = [$streetNumber, $expect];
        }

        return $entries;
    }

    /**
     * @dataProvider dataProviderStreetNumber
     */
    public function testStreetNumberSplit($streetNumber, $expected): void
    {
        $this->assertEquals($expected, OrderExport::splitStreetNumber($streetNumber));
    }

    protected function processTask(array $task): void
    {
        $this->runner->execute(
            ProcessAllTasks::getCommandName(), [],
            [ProcessAllTasks::ARG_FAIL_ON_ERROR => true]
        );

        $task = TaskModel::get($task['id']);
        $this->assertEquals(
            TaskHandler::STATUS_SUCCESS,
            $task['status'],
            'Task success'
        );
    }

    public function calculateNewOrder(array $sent_order)
    {
        $sent_order['status'] = OrderExport::STATUS_NEW;
        $sent_order['is_paid'] = false;
        $total_wt = 0;
        foreach ($sent_order['order_items'] as &$item) {
            if (!isset($item['quantity'])) {
                $item['quantity'] = 1;
            }
            if (!isset($item['value_wt'])) {
                $item['value_wt'] = round($item['quantity'] * $item['ppu_wt'], 2);
            }
            $total_wt = round($item['value_wt'] + $total_wt, 2);
        }
        $sent_order['value_wt'] = $total_wt;

        return $sent_order;
    }
}
