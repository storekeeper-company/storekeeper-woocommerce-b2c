<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Exports;

use Mockery\MockInterface;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\OrderHandler;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Tools\StringFunctions;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use WC_Helper_Order;

class OrderExportTest extends AbstractOrderExportTest
{
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
            $expect_billing['address_billing']['streetnumber'] = $new_order['billing_address_house_number'];
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
                $expect_shipping['contact_address']['streetnumber'] = $new_order['shipping_address_house_number'];
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
        $hasDifference = $exportTask->checkOrderDifference(
            new \WC_Order($order->get_id()),
            [
            'order_items' => $expected,
            'value_wt' => $this->computeOrderTotal($expected),
            ]
        );

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

    public function testOrderCreateWithNlCountry()
    {
        $this->initApiConnection();

        $this->mockApiCallsFromDirectory(self::DATA_DUMP_FOLDER_CREATE, true);

        $this->emptyEnvironment();

        $newOrderWithNlCountry = $this->getOrderProps(true);
        $newOrderWithNlCountryId = $this->createWooCommerceOrder($newOrderWithNlCountry);
        $woocommerceOrder = new \WC_Order($newOrderWithNlCountryId);
        $woocommerceOrder->update_meta_data('billing_address_house_number', $newOrderWithNlCountry['billing_address_house_number']);
        $woocommerceOrder->update_meta_data('shipping_address_house_number', $newOrderWithNlCountry['shipping_address_house_number']);
        $woocommerceOrder->save();
        $this->processNewOrder($newOrderWithNlCountryId, $newOrderWithNlCountry);
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

    public function testWooCommerceOnlyProduct()
    {
        $this->initApiConnection();
        $this->emptyEnvironment();

        $order = WC_Helper_Order::create_order();
        $order->save();

        StoreKeeperApi::$mockAdapter
            ->withModule(
                'ShopModule',
                function (MockInterface $module) {
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
                        function () {
                            return rand();
                        }
                    );

                    $module->shouldReceive('getOrder')->andReturnUsing(
                        function ($got) {
                            return [
                                'id' => current($got),
                                'status' => OrderExport::STATUS_NEW,
                                'is_paid' => false,
                            ];
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

        $this->runner->execute(ProcessAllTasks::getCommandName());

        $task = TaskModel::get($task['id']);
        $this->assertEquals(
            TaskHandler::STATUS_SUCCESS,
            $task['status'],
            'Task was marked as failed'
        );
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
        $sk_order_id = rand();
        $sk_customer_id = rand();

        StoreKeeperApi::$mockAdapter->withModule(
            'ShopModule',
            function (MockInterface $module) use ($sk_customer_id, $sk_order_id, $new_order, $new_order_id) {
                $module->shouldReceive('newOrder')
                    ->andReturnUsing(
                        function ($got) use ($new_order_id, $sk_customer_id, $new_order, $sk_order_id) {
                            $this->assertNewOrder($new_order_id, $sk_customer_id, $new_order, $got[0]);

                            return $sk_order_id;
                        }
                    );

                $module->shouldReceive('findShopCustomerBySubuserEmail')
                    ->andReturnUsing(
                        function ($got) use ($sk_customer_id, $new_order) {
                            $this->assertEquals($new_order['billing_email'], $got[0]['email']);

                            return [
                                'id' => $sk_customer_id,
                                // only this field is used
                            ];
                        }
                    );
                $module->shouldReceive('getOrder')
                    ->andReturnUsing(
                        function ($got) use ($sk_order_id) {
                            $this->assertEquals($sk_order_id, $got[0]);

                            return [
                                'id' => $sk_order_id,
                                'status' => OrderExport::STATUS_NEW,
                                'is_paid' => false,
                            ];
                        }
                    );

                /*
                 * Unrelated-calls for this test
                 */
                $module->shouldReceive('updateOrder')->andReturnUsing(
                    function () {
                        return null;
                    }
                );
            }
        );

        // run the sync
        $this->runner->execute(ProcessAllTasks::getCommandName());

        $task = TaskModel::get($task['id']);
        $this->assertEquals(
            TaskHandler::STATUS_SUCCESS,
            $task['status'],
            'Order task is succesful'
        );

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
}
