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
use Throwable;
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
     * @throws Throwable
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
                });

        // Handle the product creation hook event
        $creationOptions = $this->handleHookRequest(
            self::CREATE_DATADUMP_DIRECTORY,
            $dumpHookFile,
        );

        // Retrieve the product from wordpress using the storekeeper id
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

        $this->assertEquals($expectedStatusCallCount, $setShopProductObjectSyncStatusForHookCallCount, 'Product sync status should be sent to Backoffice');
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

        $tests['simple or configurable assign product in stock, limited, has 0 orderable stock'] = [
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

        $tests['simple or configurable assign product in stock, limited, has positive orderable stock'] = [
            [
                'type' => 'simple',
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

        $tests['simple or configurable assign product in stock, unlimited, has 0 orderable stock'] = [
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

        $tests['simple or configurable assign product in stock, limited, has negative orderable stock'] = [
            [
                'type' => 'simple',
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

        $tests['simple or configurable assign product in stock, unlimited, no orderable stock'] = [
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

        $tests['configurable product out of stock, unlimited, has no orderable stock'] = [
            [
                'type' => 'configurable',
                'product_stock' => [
                    'in_stock' => false,
                    'unlimited' => true,
                    'value' => 0,
                ],
            ],
            [
                'in_stock' => false,
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
                'manage_stock' => false,
                'quantity' => null,
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
                'manage_stock' => false,
                'quantity' => null,
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
                'manage_stock' => false,
                'quantity' => null,
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

        $productData = new Dot();
        $productData->set('flat_product.product.type', $actualData['type']);
        $productData->set('flat_product.product.product_stock.in_stock', $actualData['product_stock']['in_stock']);
        $productData->set('flat_product.product.product_stock.unlimited', $actualData['product_stock']['unlimited']);
        $productData->set('flat_product.product.product_stock.value', $actualData['product_stock']['value']);
        if (isset($actualData['orderable_stock_value'])) {
            $productData->set('orderable_stock_value', $actualData['orderable_stock_value']);
        }

        [$in_stock, $manage_stock, $stock_quantity] = $productImport->getStockProperties($productData);

        $this->assertEquals($expectedInStock, $in_stock, 'Should match expected in stock status');
        $this->assertEquals($expectedManageStock, $manage_stock, 'Should match manage stock status');
        $this->assertSame($expectedQuantity, $stock_quantity, 'Should match stock quantity');
    }

    /**
     * @throws Throwable
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
