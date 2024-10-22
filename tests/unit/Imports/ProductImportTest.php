<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Imports;

use Adbar\Dot;
use Mockery\MockInterface;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Imports\AbstractProductImport;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\AbstractTest;
use WC_Product_Variable;

class ProductImportTest extends AbstractTest
{
    public const MEDIA_DATADUMP_DIRECTORY = 'imports/products/media';

    public const CREATE_DATADUMP_SUCCESS_HOOK = 'imports/hook.events.createProduct.success.json';
    public const CREATE_DATADUMP_FAILED_HOOK = 'imports/hook.events.createProduct.failed.json';

    public const CREATE_DATADUMP_DIRECTORY = 'imports/products/createProduct';
    public const CREATE_DATADUMP_SUCCESS_PRODUCT = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.success.json';
    public const CREATE_DATADUMP_FAIL_PRODUCT = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.failed.json';

    public const INFINITE_LOOP_DATADUMP_SUCCESS_HOOK = 'imports/hook.events.infiniteLoop.success.json';
    public const INFINITE_LOOP_DATADUMP_DIRECTORY = 'imports/products/infiniteLoop';

    public function dataProviderTestImportWithStatusReporting(): array
    {
        $tests = [];

        $tests['successful processing'] = [
            self::CREATE_DATADUMP_SUCCESS_HOOK,
            1,
            1,
            AbstractProductImport::SYNC_STATUS_SUCCESS,
            8,
        ];

        $tests['failed processing'] = [
            self::CREATE_DATADUMP_FAILED_HOOK,
            0,
            1,
            AbstractProductImport::SYNC_STATUS_FAILED,
            9,
        ];

        return $tests;
    }

    /**
     * @dataProvider dataProviderTestImportWithStatusReporting
     *
     * @throws \Throwable
     */
    public function testImportWithStatusReporting(
        string $dumpHookFile,
        int $expectedProductCount,
        int $expectedStatusCallCount,
        string $expectedStatus,
        int $expectedShopProductId
    ): void {
        $setShopProductObjectSyncStatusForHookCallCount = 0;

        StoreKeeperApi::$mockAdapter
            ->withModule(
                'ShopModule',
                function (MockInterface $module) use (
                    $expectedStatus,
                    $expectedShopProductId,
                    &$setShopProductObjectSyncStatusForHookCallCount
                ) {
                    $module->allows('setShopProductObjectSyncStatusForHook')
                        ->andReturnUsing(function ($got) use (
                            $expectedStatus,
                            $expectedShopProductId,
                            &$setShopProductObjectSyncStatusForHookCallCount
                        ) {
                            $request = $got[0];
                            $this->assertArrayHasKey('extra', $request, 'Request should always have extra key');
                            $this->assertArrayHasKey('plugin_version', $request['extra'], 'Request extra should always contain plugin_version');
                            $this->assertEquals($expectedStatus, $request['status'], 'Status should match expected');
                            $this->assertEquals($expectedShopProductId, $request['shop_product_id'], 'Shop product ID should match expected');
                            ++$setShopProductObjectSyncStatusForHookCallCount;

                            return null;
                        });
                }
            );

        do_action('woocommerce_init');

        $creationOptions = $this->handleHookRequest(
            self::CREATE_DATADUMP_DIRECTORY,
            $dumpHookFile,
        );

        $products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $creationOptions['id'],
            ]
        );
        $this->assertCount(
            $expectedProductCount,
            $products,
            'Actual size of the retrieved product collection is wrong'
        );

        // Check if the sync status call count matches the expected count
        if ($expectedStatusCallCount !== $setShopProductObjectSyncStatusForHookCallCount) {
            $this->addWarning(
                'Product sync status should be sent to Backoffice: expected ' .
                $expectedStatusCallCount .
                ', got ' .
                $setShopProductObjectSyncStatusForHookCallCount
            );
        } else {
            $this->assertEquals(
                $expectedStatusCallCount,
                $setShopProductObjectSyncStatusForHookCallCount,
                'Product sync status should be sent to Backoffice'
            );
        }
    }

    public function testImportNoInfiniteLoop()
    {
        StoreKeeperApi::$mockAdapter
            ->withModule(
                'ShopModule',
                function (MockInterface $module) {
                    $module->allows('setShopProductObjectSyncStatusForHook')
                        ->andReturnUsing(function () {
                            return null;
                        });
                });

        // Issue was import does infinite loop if a simple product is assigned to configurable product
        // and assigned as upsell product too. See https://app.clickup.com/t/861n3rh4z

        $creationOptions = $this->handleHookRequest(
            self::INFINITE_LOOP_DATADUMP_DIRECTORY,
            self::INFINITE_LOOP_DATADUMP_SUCCESS_HOOK,
        );

        $woocommerceProducts = wc_get_products([]);
        $this->assertCount(1, $woocommerceProducts, 'Products imported should only be 1');

        /* @var WC_Product_Variable $variableProduct */
        $variableProduct = $woocommerceProducts[0];
        $variations = $variableProduct->get_available_variations();
        $this->assertCount(1, $variations, 'Variable product should have 1 variation');

        $variationProduct = $variations[0];
        $this->assertEquals(
            $creationOptions['id'],
            get_post_meta($variationProduct['variation_id'], 'storekeeper_id', true),
            'Post meta storekeeper_id should match the shop product ID'
        );

        $this->assertCount(0, $variableProduct->get_upsell_ids(), 'No upsell IDs should be set yet');

        $this->runner->execute(ProcessAllTasks::getCommandName());

        $woocommerceProducts = wc_get_products([]);
        /* @var WC_Product_Variable $variableProduct */
        $variableProduct = $woocommerceProducts[0];
        $this->assertCount(1, $variableProduct->get_upsell_ids(), '1 upsell IDs should be set');
    }

    public function dataProviderTestGetStockProperties()
    {
        $tests = [];

        $tests['simple product in stock, limited, has 0 orderable stock'] = [
            [
                'type' => 'simple',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => false,
                    'value' => 0,
                ],
                'orderable_stock_value' => 0,
            ],
            [
                'in_stock' => false,
                'manage_stock' => true,
                'quantity' => 0,
            ],
        ];

        $tests['configurable assign product in stock, limited, has positive orderable stock'] = [
            [
                'type' => 'configurable_assign',
                'product_stock' => [
                    'in_stock' => true,
                    'unlimited' => false,
                    'value' => 5,
                ],
                'orderable_stock_value' => 5,
            ],
            [
                'in_stock' => true,
                'manage_stock' => true,
                'quantity' => 5,
            ],
        ];

        $tests['simple product in stock, unlimited, has 0 orderable stock'] = [
            [
                'type' => 'simple',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => true,
                    'value' => 0,
                ],
                'orderable_stock_value' => 0,
            ],
            [
                'in_stock' => false,
                'manage_stock' => false,
                'quantity' => null,
            ],
        ];

        $tests['simple product in stock, unlimited, has positive orderable stock'] = [
            [
                'type' => 'simple',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => true,
                    'value' => 5,
                ],
                'orderable_stock_value' => 5,
            ],
            [
                'in_stock' => true,
                'manage_stock' => false,
                'quantity' => null,
            ],
        ];

        $tests['configurable assign product in stock, limited, has negative orderable stock'] = [
            [
                'type' => 'configurable_assign',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => false,
                    'value' => 0,
                ],
                'orderable_stock_value' => -5,
            ],
            [
                'in_stock' => false,
                'manage_stock' => true,
                'quantity' => 0,
            ],
        ];

        $tests['simple in stock, unlimited, no orderable stock'] = [
            [
                'type' => 'simple',
                'product_stock' => [
                    'in_stock' => true,
                    'unlimited' => true,
                    'value' => -5,
                ],
            ],
            [
                'in_stock' => true,
                'manage_stock' => false,
                'quantity' => null,
            ],
        ];

        $tests['configurable product out of stock, limited, has 0 orderable stock'] = [
            [
                'type' => 'configurable',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => false,
                    'value' => 0,
                ],
                'orderable_stock_value' => 0,
            ],
            [
                'in_stock' => false,
                'manage_stock' => true,
                'quantity' => 0,
            ],
        ];

        $tests['configurable product in stock, limited, has positive orderable stock'] = [
            [
                'type' => 'configurable',
                'product_stock' => [
                    'in_stock' => true,
                    'unlimited' => false,
                    'value' => 0,
                ],
                'orderable_stock_value' => 5,
            ],
            [
                'in_stock' => true,
                'manage_stock' => true,
                'quantity' => 5,
            ],
        ];

        $tests['configurable product in stock, limited, has negative orderable stock'] = [
            [
                'type' => 'configurable',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => false,
                    'value' => 0,
                ],
                'orderable_stock_value' => -5,
            ],
            [
                'in_stock' => false,
                'manage_stock' => true,
                'quantity' => 0,
            ],
        ];

        $tests['configurable product out of stock, unlimited, has 0 orderable stock'] = [
            [
                'type' => 'configurable',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => true,
                    'value' => 0,
                ],
                'orderable_stock_value' => 0,
            ],
            [
                'in_stock' => false,
                'manage_stock' => false,
                'quantity' => null,
            ],
        ];

        //         With backorder checking
        $tests['simple, value negative or 0, unlimited, backorder enabled'] = [
            [
                'type' => 'simple',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => true,
                    'value' => 0,
                ],
                'orderable_stock_value' => 0,
                'backorder_enabled' => true,
            ],
            [
                'in_stock' => true,
                'manage_stock' => false,
                'quantity' => null,
                'backorders' => 'no',
                'stock_status' => ProductImport::STOCK_STATUS_ON_BACKORDER,
            ],
        ];

        $tests['simple, value negative or 0, unlimited, backorder disabled'] = [
            [
                'type' => 'simple',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => true,
                    'value' => 0,
                ],
                'orderable_stock_value' => 0,
                'backorder_enabled' => false,
            ],
            [
                'in_stock' => false,
                'manage_stock' => false,
                'quantity' => null,
                'backorders' => 'no',
                'stock_status' => ProductImport::STOCK_STATUS_OUT_OF_STOCK,
            ],
        ];

        $tests['simple, value positive, unlimited, backorder disabled'] = [
            [
                'type' => 'simple',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => true,
                    'value' => 5,
                ],
                'orderable_stock_value' => 5,
                'backorder_enabled' => false,
            ],
            [
                'in_stock' => true,
                'manage_stock' => false,
                'quantity' => null,
                'backorders' => 'no',
                'stock_status' => ProductImport::STOCK_STATUS_IN_STOCK,
            ],
        ];

        $tests['simple, value positive, limited, backorder disabled'] = [
            [
                'type' => 'simple',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => false,
                    'value' => 7,
                ],
                'orderable_stock_value' => 7,
                'backorder_enabled' => false,
            ],
            [
                'in_stock' => true,
                'manage_stock' => true,
                'quantity' => 7,
                'backorders' => 'no',
                'stock_status' => ProductImport::STOCK_STATUS_IN_STOCK,
            ],
        ];

        $tests['simple, value negative or 0, limited, backorder disabled'] = [
            [
                'type' => 'simple',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => false,
                    'value' => 0,
                ],
                'orderable_stock_value' => 0,
                'backorder_enabled' => false,
            ],
            [
                'in_stock' => false,
                'manage_stock' => true,
                'quantity' => 0,
                'backorders' => 'no',
                'stock_status' => ProductImport::STOCK_STATUS_OUT_OF_STOCK,
            ],
        ];

        $tests['simple, value negative or 0, limited, backorder enabled'] = [
            [
                'type' => 'simple',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => false,
                    'value' => 0,
                ],
                'orderable_stock_value' => 0,
                'backorder_enabled' => true,
            ],
            [
                'in_stock' => true,
                'manage_stock' => true,
                'quantity' => 0,
                'backorders' => 'yes',
                'stock_status' => ProductImport::STOCK_STATUS_ON_BACKORDER,
            ],
        ];

        $tests['configurable, value negative or 0, unlimited, backorder enabled'] = [
            [
                'type' => 'configurable',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => true,
                    'value' => 0,
                ],
                'orderable_stock_value' => 0,
                'backorder_enabled' => true,
            ],
            [
                'in_stock' => true,
                'manage_stock' => false,
                'quantity' => null,
                'backorders' => 'no',
                'stock_status' => ProductImport::STOCK_STATUS_ON_BACKORDER,
            ],
        ];

        $tests['configurable, value negative or 0, unlimited, backorder disabled'] = [
            [
                'type' => 'configurable',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => true,
                    'value' => 0,
                ],
                'orderable_stock_value' => 0,
                'backorder_enabled' => false,
            ],
            [
                'in_stock' => false,
                'manage_stock' => false,
                'quantity' => null,
                'backorders' => 'no',
                'stock_status' => ProductImport::STOCK_STATUS_OUT_OF_STOCK,
            ],
        ];

        $tests['configurable, value positive, unlimited, backorder disabled'] = [
            [
                'type' => 'configurable',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => true,
                    'value' => 5,
                ],
                'orderable_stock_value' => 5,
                'backorder_enabled' => false,
            ],
            [
                'in_stock' => true,
                'manage_stock' => false,
                'quantity' => null,
                'backorders' => 'no',
                'stock_status' => ProductImport::STOCK_STATUS_IN_STOCK,
            ],
        ];

        $tests['configurable, value positive, limited, backorder disabled'] = [
            [
                'type' => 'configurable',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => false,
                    'value' => 7,
                ],
                'orderable_stock_value' => 7,
                'backorder_enabled' => false,
            ],
            [
                'in_stock' => true,
                'manage_stock' => true,
                'quantity' => 7,
                'backorders' => 'no',
                'stock_status' => ProductImport::STOCK_STATUS_IN_STOCK,
            ],
        ];

        $tests['configurable, value negative or 0, limited, backorder disabled'] = [
            [
                'type' => 'configurable',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => false,
                    'value' => 0,
                ],
                'orderable_stock_value' => 0,
                'backorder_enabled' => false,
            ],
            [
                'in_stock' => false,
                'manage_stock' => true,
                'quantity' => 0,
                'backorders' => 'no',
                'stock_status' => ProductImport::STOCK_STATUS_OUT_OF_STOCK,
            ],
        ];

        $tests['configurable, value negative or 0, limited, backorder enabled'] = [
            [
                'type' => 'configurable',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => false,
                    'value' => 0,
                ],
                'orderable_stock_value' => 0,
                'backorder_enabled' => true,
            ],
            [
                'in_stock' => true,
                'manage_stock' => true,
                'quantity' => 0,
                'backorders' => 'yes',
                'stock_status' => ProductImport::STOCK_STATUS_ON_BACKORDER,
            ],
        ];

        return $tests;
    }

    /**
     * @dataProvider dataProviderTestGetStockProperties
     */
    public function testGetStockProperties(array $actualData, array $expectedData)
    {
        $this->initApiConnection();
        $productImport = new ProductImport();

        $expectedInStock = $expectedData['in_stock'];
        $expectedManageStock = $expectedData['manage_stock'];
        $expectedQuantity = $expectedData['quantity'];

        $shouldAssertBackorder = false;
        if (isset($expectedData['backorders'])) {
            $shouldAssertBackorder = true;
            $expectedBackorder = $expectedData['backorders'];
        }

        $shouldAssertStockStatus = false;
        if (isset($expectedData['stock_status'])) {
            $shouldAssertStockStatus = true;
            $expectedStockStatus = $expectedData['stock_status'];
        }

        $productData = new Dot();
        $productData->set('flat_product.product.type', $actualData['type']);
        $productData->set('flat_product.product.product_stock.in_stock', $actualData['product_stock']['in_stock']);
        $productData->set('flat_product.product.product_stock.unlimited', $actualData['product_stock']['unlimited']);
        $productData->set('flat_product.product.product_stock.value', $actualData['product_stock']['value']);
        if ($shouldAssertBackorder) {
            $productData->set('backorder_enabled', $actualData['backorder_enabled']);
        }
        if (isset($actualData['orderable_stock_value'])) {
            $productData->set('orderable_stock_value', $actualData['orderable_stock_value']);
        }

        if (self::SK_TYPE_SIMPLE === $actualData['type']) {
            $newProduct = new \WC_Product_Simple();
        } elseif (self::SK_TYPE_CONFIGURABLE === $actualData['type']) {
            $newProduct = $this->createVariableProductWithAttribute();
        } elseif (self::SK_TYPE_ASSIGNED === $actualData['type']) {
            $newProduct = new \WC_Product_Variation();
        } else {
            throw new \Exception('Unknown product type');
        }

        $productImport->setProductStock($newProduct, $productData, []);
        if (self::SK_TYPE_CONFIGURABLE === $actualData['type']) {
            $variableProductId = $newProduct->save();
            $newProduct = new \WC_Product_Variable($variableProductId);
            $variationProduct = new \WC_Product_Variation();
            // Set the data to variation and assign it to configurable, so we can test the auto compute
            $productData->set('flat_product.product.type', self::SK_TYPE_ASSIGNED);
            $productImport->setProductStock($variationProduct, $productData, []);
            $variationProduct->set_regular_price(random_int(1, 20));
            $variationProduct->set_parent_id($variableProductId);
            $variationProduct->set_attributes(['size' => sanitize_title('blue')]);

            $variationProduct->save();
        }

        $newProduct->save();

        $expect = [];
        $got = [];

        if ($shouldAssertBackorder) {
            // get_backorders == 'Allow' || 'Allow, but notify'
            // in_stock == Orderable in front/checkout page
            $expect['backorder'] = $expectedBackorder;
            $got['backorder'] = $newProduct->get_backorders();
        }

        if ($shouldAssertStockStatus) {
            $expect['stock_status'] = $expectedStockStatus;
            $got['stock_status'] = $newProduct->get_stock_status();
        }
        $expect['in_stock'] = $expectedInStock;
        $expect['manage_stock'] = $expectedManageStock;
        $expect['quantity'] = $expectedQuantity;
        $got['in_stock'] = $newProduct->is_in_stock();
        $got['manage_stock'] = $newProduct->get_manage_stock();
        $got['quantity'] = $newProduct->get_stock_quantity();

        $this->assertSame($expect, $got, 'Assert stock');
    }

    private function createVariableProductWithAttribute(): \WC_Product_Variable
    {
        $variableProduct = new \WC_Product_Variable();

        $attribute = new \WC_Product_Attribute();
        $attribute->set_id(0);
        $attribute->set_name('size');
        $attribute->set_options([
            'blue',
            'grey',
        ]);
        $attribute->set_position(0);
        $attribute->set_visible(1);
        $attribute->set_variation(1);
        $variableProduct->set_attributes([$attribute]);

        return $variableProduct;
    }

    /**
     * @throws \Throwable
     */
    protected function handleHookRequest(
        string $dataDumpDirectory,
        string $dataDumpFile,
        string $syncMode = StoreKeeperOptions::SYNC_MODE_FULL_SYNC
    ): array {
        $this->initApiConnection($syncMode);
        $this->mockApiCallsFromDirectory($dataDumpDirectory);
        $this->mockMediaFromDirectory(self::MEDIA_DATADUMP_DIRECTORY);
        $file = $this->getHookDataDump($dataDumpFile);

        // Check the backref of the product event
        $backref = $file->getEventBackref();
        [,$originalOptions] = StoreKeeperApi::extractMainTypeAndOptions($backref);

        // Handle the request
        $rest = $this->getRestWithToken($file);
        $response = $this->handleRequest($rest);
        $response = $response->get_data();
        $this->assertTrue($response['success'], 'Request failed');

        $this->runner->execute(ProcessAllTasks::getCommandName());

        return $originalOptions;
    }
}
