<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use Adbar\Dot;
use Mockery\MockInterface;
use org\bovigo\vfs\vfsStream;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceFeaturedAttributes;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceProducts;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceSingleProduct;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;
use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeOptionModel;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Media;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

class SyncWoocommerceProductsTest extends AbstractTest
{
    // Datadump related constants
    const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-products';

    const WITH_ERRORS_DATADUMP_DIRECTORY = 'commands/sync-woocommerce-products/with-errors';

    const DATADUMP_SOURCE_FILE = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.72e551759ae4651bdb99611a255078af300eb8b787c2a8b9a216b800b8818b06.json';
    const DATADUMP_PRODUCT_21_FILE = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.success.613db2c03f849.json';
    const DATADUMP_IMAGE_PRODUCT_FILE = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.success.62c2cdd392106.json';
    const DATADUMP_CONFIGURABLE_OPTIONS_FILE = 'moduleFunction.ShopModule::getConfigurableShopProductOptions.5e20566c4b0dd01fa60732d6968bc565b60fbda96451d989d00e35cc6d46e04a.json';

    const MEDIA_IMAGE_JPEG_FILE = 'image_big_image.jpeg';
    const MEDIA_CAT_SAMPLE_IMAGE_JPEG_FILE = 'cat_sample_big_image.jpg';

    /**
     * Initialize the tests by following these steps:
     * 1. Initialize the API connection and the mock API calls
     * 2. Make sure there are no products imported
     * 3. Run the 'wp sk sync-woocommerce-products' command
     * 4. Run the 'wp sk process-all-tasks' command to process the tasks spawned by the import ( parent recalculation ).
     *
     * @throws \Throwable
     */
    protected function initializeTest($storekeeperId = null)
    {
        $imageCdnPrefix = 'testPrefix';
        // Initialize the test
        $this->initApiConnection();
        $this->prepareVFSForCDNImageTest($imageCdnPrefix);
        $this->mockSyncWoocommerceShopInfo($imageCdnPrefix);

        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY, true);
        $this->mockApiCallsFromCommonDirectory();
        $this->mockMediaFromDirectory(self::DATADUMP_DIRECTORY.'/media');
        $this->runner->execute(SyncWoocommerceFeaturedAttributes::getCommandName());

        // Tests whether there are no products before import
        $wc_products = wc_get_products([]);
        $this->assertCount(
            0,
            $wc_products,
            'Test was not ran in an empty environment'
        );

        if (!is_null($storekeeperId)) {
            $this->runner->addCommandClass(SyncWoocommerceSingleProduct::class);
            $this->runner->execute(SyncWoocommerceSingleProduct::getCommandName(), [
            ], [
                'storekeeper_id' => $storekeeperId,
            ]);
        } else {
            // Run the product import command
            $this->runner->execute(SyncWoocommerceProducts::getCommandName());
        }

        // Process all the tasks that get spawned by the product import command
        $this->runner->execute(ProcessAllTasks::getCommandName());
    }

    /**
     * Fetch the data from the datadump source file.
     */
    protected function getReturnData($file = self::DATADUMP_SOURCE_FILE): array
    {
        // Read the original data from the data dump
        $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.$file);

        return $file->getReturn()['data'];
    }

    public function testSimpleProductImport()
    {
        $this->initializeTest();

        $original_product_data = $this->getReturnData();

        // Get the simple products from the data dump
        $original_product_data = $this->getProductsByTypeFromDataDump(
            $original_product_data,
            self::SK_TYPE_SIMPLE
        );

        // Retrieve all synchronised simple products
        $wc_simple_products = wc_get_products(['type' => self::WC_TYPE_SIMPLE]);
        $this->assertSameSize(
            $original_product_data,
            $wc_simple_products,
            'Amount of synchronised simple products doesn\'t match source data'
        );

        foreach ($original_product_data as $product_data) {
            $original = new Dot($product_data);

            // Retrieve product(s) with the storekeeper_id from the source data
            $wc_products = wc_get_products(
                [
                    'post_type' => 'product',
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $original->get('id'),
                ]
            );
            $this->assertCount(
                1,
                $wc_products,
                'More then one product found with the provided storekeeper_id'
            );

            // Get the simple product with the storekeeper_id
            $wc_simple_product = $wc_products[0];
            $this->assertEquals(
                self::WC_TYPE_SIMPLE,
                $wc_simple_product->get_type(),
                'WooCommerce product type doesn\'t match the expected product type'
            );

            $this->assertProduct($original, $wc_simple_product);

            // Test the products with emballage
            $wcProductEmballagePrice = $wc_simple_product->get_meta(ProductImport::PRODUCT_EMBALLAGE_PRICE_META_KEY);
            $wcProductEmballagePriceWT = $wc_simple_product->get_meta(ProductImport::PRODUCT_EMBALLAGE_PRICE_WT_META_KEY);
            $wcProductEmballageTaxId = $wc_simple_product->get_meta(ProductImport::PRODUCT_EMBALLAGE_TAX_ID_META_KEY);
            $originalProductEmballagePrice = $original->get('product_emballage_price.ppu');
            $originalProductEmballagePriceWt = $original->get('product_emballage_price.ppu_wt');
            $originalProductEmballageTaxRateId = $original->get('product_emballage_price.tax_rate_id');

            $this->assertEquals($originalProductEmballagePrice, $wcProductEmballagePrice, 'Emballage price without tax should match');
            $this->assertEquals($wcProductEmballagePriceWT, $originalProductEmballagePriceWt, 'Emballage price with tax should match');
            $this->assertEquals($wcProductEmballageTaxId, $originalProductEmballageTaxRateId, 'Emballage price tax ID should match');
        }

        $this->assertAttributeOptionOrder();
    }

    public function testProductImageImport()
    {
        $imageCdnPrefix = 'testPrefix';
        $this->prepareVFSForCDNImageTest($imageCdnPrefix);
        $this->mockSyncWoocommerceShopInfo($imageCdnPrefix);

        $this->assertEmpty(StoreKeeperOptions::get(StoreKeeperOptions::IMAGE_CDN_PREFIX), 'CDN prefix should be empty initially');

        $storekeeperProductId = 20;
        // Set CDN to false first
        StoreKeeperOptions::set(StoreKeeperOptions::IMAGE_CDN, 'no');
        $this->initializeTest($storekeeperProductId);

        $originalProductData = $this->getReturnData(self::DATADUMP_IMAGE_PRODUCT_FILE);

        // Get the simple products from the data dump
        $originalProductData = $this->getProductsByTypeFromDataDump(
            $originalProductData,
            self::SK_TYPE_SIMPLE
        );

        // Test if image is downloaded
        $this->assertDownloadedImage($originalProductData);

        // Set CDN to true
        StoreKeeperOptions::set(StoreKeeperOptions::IMAGE_CDN, 'yes');
        $syncCommand = new SyncWoocommerceSingleProduct();
        $syncCommand->runSync([
            'storekeeper_id' => $storekeeperProductId,
        ]);

        $this->assertCdnImage($originalProductData, $imageCdnPrefix);
        $this->assertEquals($imageCdnPrefix, StoreKeeperOptions::get(StoreKeeperOptions::IMAGE_CDN_PREFIX), 'CDN prefix should be synchronized from shop info');

        // Set CDN to false again
        StoreKeeperOptions::set(StoreKeeperOptions::IMAGE_CDN, 'no');
        $syncCommand = new SyncWoocommerceSingleProduct();
        $syncCommand->runSync([
            'storekeeper_id' => $storekeeperProductId,
        ]);

        // Test if image is downloaded again
        $this->assertDownloadedImage($originalProductData);
    }

    public function testOrderableSimpleProductStock()
    {
        $productStorekeeperId = 22;
        $this->initializeTest($productStorekeeperId);

        $wooCommerceProducts = wc_get_products(['type' => self::WC_TYPE_SIMPLE]);

        $this->assertCount(1, $wooCommerceProducts, 'Error in test, multiple products imported');
        $wooCommerceProduct = $wooCommerceProducts[0];
        $expected = [
            'sku' => 'MWVR2ORDERABLE',
            'manage_stock' => true,
            'stock_quantity' => 0, // in reality -15, but we force set to 0
            'stock_status' => 'onbackorder', // because "backorder_enabled": true,
        ];
        $actual = [
            'sku' => $wooCommerceProduct->get_sku(),
            'manage_stock' => $wooCommerceProduct->get_manage_stock(ProductImport::EDIT_CONTEXT),
            'stock_quantity' => $wooCommerceProduct->get_stock_quantity(),
            'stock_status' => $wooCommerceProduct->get_stock_status(),
        ];
        $this->assertEquals($expected, $actual);
    }

    public function testOrderableConfigurableProductStock()
    {
        $productStorekeeperId = 23;
        $this->initializeTest($productStorekeeperId);

        $wooCommerceProducts = wc_get_products(['type' => self::WC_TYPE_CONFIGURABLE]);

        $this->assertCount(1, $wooCommerceProducts, 'Error in test, multiple products imported');
        /** @var \WC_Product_Variable $wooCommerceProduct */
        $wooCommerceProduct = $wooCommerceProducts[0];
        $wooCommerceProduct->validate_props();
        $expected = [
            'sku' => 'MWVR2OCONFIG',
            'manage_stock' => false, // Configurable stocks are not being managed
            'stock_quantity' => null,
            'stock_status' => 'instock',
        ];
        $actual = [
            'sku' => $wooCommerceProduct->get_sku(),
            'manage_stock' => $wooCommerceProduct->get_manage_stock(ProductImport::EDIT_CONTEXT),
            'stock_quantity' => $wooCommerceProduct->get_stock_quantity(),
            'stock_status' => $wooCommerceProduct->get_stock_status(),
        ];
        $this->assertEquals($expected, $actual, 'stock of configurable product');

        $assigned_stock_expected = [
            'MWVR2-in-stock-75' => [
                'manage_stock' => true,
                'stock_quantity' => 75,
                'stock_status' => 'instock',
            ],
            'MWVR2-out-of-stock' => [
                'manage_stock' => true,
                'stock_quantity' => 0, // in reality -10
                'stock_status' => 'onbackorder', // because "backorder_enabled": true,
            ],
        ];
        $assigned_stock_actual = [];
        foreach ($wooCommerceProduct->get_visible_children() as $childId) {
            $variationProduct = new \WC_Product_Variation($childId);
            $assigned_stock_actual[$variationProduct->get_sku()] = [
                'manage_stock' => $variationProduct->get_manage_stock(ProductImport::EDIT_CONTEXT),
                'stock_quantity' => $variationProduct->get_stock_quantity(),
                'stock_status' => $variationProduct->get_stock_status(),
            ];
        }
        ksort($assigned_stock_actual);

        $this->assertEquals($assigned_stock_expected, $assigned_stock_actual, 'stock of assigned products');
    }

    public function testAttributesAndOptionsOrder()
    {
        $productStorekeeperId = 21;
        $this->initializeTest($productStorekeeperId);

        // Create a collection with all of the variations of configurable products from WooCommerce
        $actualAttributesPosition = [];
        $wooCommerceConfigurableProduct = current(wc_get_products(['type' => self::WC_TYPE_CONFIGURABLE]));

        $parentProduct = new \WC_Product_Variable($wooCommerceConfigurableProduct->get_id());
        $wooCommerceAttributes = $parentProduct->get_attributes();
        foreach ($wooCommerceAttributes as $wooCommerceAttribute) {
            /* @var $wooCommerceAttribute \WC_Product_Attribute */
            $attributeName = $this->cleanAttributeName($wooCommerceAttribute->get_name());
            $actualAttributesPosition[$attributeName] = $wooCommerceAttribute->get_position();
        }

        $this->assertEquals([
            'barcode' => 4,
            'kleur' => 3,
        ], $actualAttributesPosition);

        $actualAttributeOptionPosition = [];
        foreach ($parentProduct->get_visible_children() as $childId) {
            $variationProduct = new \WC_Product_Variation($childId);
            $actualAttributeOptionPosition[$variationProduct->get_sku()] = $variationProduct->get_menu_order();
        }
        $this->assertEquals(
            [
                'MWVR2-wit' => 3,
                'MWVR2-zwart' => 4,
            ],
            $actualAttributeOptionPosition,
            'Menu order for '.$variationProduct->get_title()
        );

        $this->assertAttributeOptionOrder();
    }

    public function cleanAttributeName($name)
    {
        $name = str_replace(['attribute_', 'pa_'], '', $name);

        return strtolower(trim($name));
    }

    public function getAttributeWithPositions($products)
    {
        $positions = [];
        foreach ($products as $product) {
            $dot = new Dot($product);
            $contentVars = $dot->get('flat_product.content_vars');
            foreach ($contentVars as $data) {
                $contentVar = new Dot($data);
                $attributeName = $this->cleanAttributeName($contentVar->get('name'));
                $positions[$attributeName] = $contentVar->get('attribute_order');
            }
        }

        return $positions;
    }

    public function testConfigurableProductImport()
    {
        $this->initializeTest();

        $original_product_data = $this->getReturnData();

        // Get the configurable products from the data dump
        $original_product_data = $this->getProductsByTypeFromDataDump(
            $original_product_data,
            self::SK_TYPE_CONFIGURABLE
        );

        // Retrieve all synchronised configurable products
        $wc_configurable_products = wc_get_products(['type' => self::WC_TYPE_CONFIGURABLE]);
        $this->assertSameSize(
            $original_product_data,
            $wc_configurable_products,
            'Amount of synchronised configurable products doesn\'t match source data'
        );

        $this->assertAttributeOptionOrder();

        foreach ($original_product_data as $product_data) {
            $original = new Dot($product_data);

            // Retrieve product(s) with the storekeeper_id from the source data
            $wc_products = wc_get_products(
                [
                    'post_type' => 'product',
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $original->get('id'),
                ]
            );
            $this->assertCount(
                1,
                $wc_products,
                'More then one product found with the provided storekeeper_id'
            );

            // Get the simple product with the storekeeper_id
            $wc_configurable_product = $wc_products[0];
            $this->assertEquals(
                self::WC_TYPE_CONFIGURABLE,
                $wc_configurable_product->get_type(),
                'WooCommerce product type doesn\'t match the expected product type'
            );

            $this->assertProduct($original, $wc_configurable_product);
        }
    }

    protected function assertAttributeOptionOrder()
    {
        // Read the original data from the data dump
        $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.self::DATADUMP_CONFIGURABLE_OPTIONS_FILE);
        $configurableProductOptions = $file->getReturn();
        $attributeOptions = $configurableProductOptions['attribute_options'];

        foreach ($attributeOptions as $attributeOption) {
            $woocommerceAttribute = AttributeModel::getAttributeByStoreKeeperId($attributeOption['attribute_id']);

            $termId = AttributeOptionModel::getTermIdByStorekeeperId(
                $woocommerceAttribute->id,
                $attributeOption['id']
            );

            $woocommerceOptionOrder = get_term_meta($termId, 'order', true);
            $storekeeperOptionOrder = $attributeOption['order'];

            $this->assertEquals($storekeeperOptionOrder, $woocommerceOptionOrder, 'Product option order did not update');
        }
    }

    public function testAssignedProductImport()
    {
        $this->initializeTest();

        $original_product_data = $this->getReturnData();

        // Get the assigned products from the data dump
        $original_product_data = $this->getProductsByTypeFromDataDump(
            $original_product_data,
            self::SK_TYPE_ASSIGNED
        );

        // Create a collection with all of the variations of configurable products from WooCommerce
        $wc_assigned_products = [];
        $wc_configurable_products = wc_get_products(['type' => self::WC_TYPE_CONFIGURABLE]);
        foreach ($wc_configurable_products as $wc_configurable_product) {
            $parent_product = new \WC_Product_Variable($wc_configurable_product->get_id());
            foreach ($parent_product->get_visible_children() as $index => $visible_child_id) {
                $wc_variation_product = new \WC_Product_Variation($visible_child_id);
                $storekeeper_id = $wc_variation_product->get_meta('storekeeper_id');
                $wc_assigned_products[$storekeeper_id] = $wc_variation_product;
            }
        }

        foreach ($original_product_data as $product_data) {
            $original = new Dot($product_data);

            // Retrieve assigned product with the storekeeper_id from the source data
            $wc_assigned_product = $wc_assigned_products[$original->get('id')];
            $this->assertNotEmpty($wc_assigned_product, 'No assigned product with the given storekeeper id');

            $this->assertProduct($original, $wc_assigned_product);
        }
    }

    public function testImportErrorLogging()
    {
        $imageCdnPrefix = 'testPrefix';
        // Initialize the test
        $this->initApiConnection();
        $this->prepareVFSForCDNImageTest($imageCdnPrefix);
        $this->mockSyncWoocommerceShopInfo($imageCdnPrefix);

        // We don't need CDN for this test
        StoreKeeperOptions::set(StoreKeeperOptions::IMAGE_CDN, 'no');

        $this->mockApiCallsFromDirectory(self::WITH_ERRORS_DATADUMP_DIRECTORY);
        $this->mockApiCallsFromCommonDirectory();
        $this->mockMediaFromDirectory(self::DATADUMP_DIRECTORY.'/media');
        $this->runner->execute(SyncWoocommerceFeaturedAttributes::getCommandName());

        // Run the product import command
        $this->runner->execute(SyncWoocommerceProducts::getCommandName());

        // Process all the tasks that get spawned by the product import command
        $this->runner->execute(ProcessAllTasks::getCommandName());
    }

    protected function assertDownloadedImage(array $originalProductData): void
    {
        foreach ($originalProductData as $productData) {
            $original = new Dot($productData);

            // Retrieve product(s) with the storekeeper_id from the source data
            $wcProducts = wc_get_products(
                [
                    'post_type' => 'product',
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $original->get('id'),
                ]
            );

            // Get the simple product with the storekeeper_id
            /* @var \WC_Product $wcSimpleProduct */
            $wcSimpleProduct = $wcProducts[0];

            $attachmentId = $wcSimpleProduct->get_image_id();
            $this->assertEmpty(get_post_meta($attachmentId, 'is_cdn', true), 'Attachment should be downloaded');
            $attachmentUrl = wp_get_attachment_image_url($attachmentId);
            $this->assertTrue(Media::hasUploadDirectory($attachmentUrl), 'Attachment does not have wordpress upload directory in path');

            $attachmentImageSrcSet = wp_get_attachment_image_srcset($attachmentId);
            $attachmentImageSrcSet = explode(',', $attachmentImageSrcSet);
            foreach ($attachmentImageSrcSet as $attachmentImageSrc) {
                $this->assertTrue(Media::hasUploadDirectory($attachmentImageSrc), 'Attachment image src set is not valid');
            }
        }
    }

    protected function assertCdnImage(array $originalProductData, string $imageCdnPrefix): void
    {
        foreach ($originalProductData as $productData) {
            $original = new Dot($productData);

            // Retrieve product(s) with the storekeeper_id from the source data
            $wcProducts = wc_get_products(
                [
                    'post_type' => 'product',
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $original->get('id'),
                ]
            );

            // Get the simple product with the storekeeper_id
            /* @var \WC_Product $wcSimpleProduct */
            $wcSimpleProduct = $wcProducts[0];
            $attachmentId = $wcSimpleProduct->get_image_id();
            $this->assertTrue((bool) get_post_meta($attachmentId, 'is_cdn', true), 'Attachment should be external');

            $attachmentUrl = wp_get_attachment_image_url($attachmentId, [10000, 10000]);
            $originalCdnUrl = $original->get('flat_product.main_image.cdn_url');
            $originalUrl = str_replace(Media::CDN_URL_VARIANT_PLACEHOLDER_KEY, "{$imageCdnPrefix}.".Media::FULL_VARIANT_KEY, $originalCdnUrl);
            $this->assertEquals($originalUrl, $attachmentUrl, 'Original URL is not same with attachment URL');

            $attachmentImageSrcSet = (string) wp_get_attachment_image_srcset($attachmentId);
            $attachmentImageSrcSetArray = explode(', ', $attachmentImageSrcSet);
            if (!empty($attachmentImageSrcSet)) {
                foreach ($attachmentImageSrcSetArray as $attachmentImageSrc) {
                    // Pattern will be https:\/\/cdn_url\/path\/[0-9a-zA-Z]+\.[0-9a-zA-Z_]+\/filename size
                    $pattern = str_replace(Media::CDN_URL_VARIANT_PLACEHOLDER_KEY, '[0-9a-zA-Z]+\.[0-9a-zA-Z_]+', $originalCdnUrl).' [0-9]+w';
                    $pattern = str_replace('/', '\/', $pattern);
                    $this->assertTrue((bool) preg_match("/$pattern/", $attachmentImageSrc), 'Attachment image src set is not valid');
                }
            }
        }
    }

    /**
     * @throws \Exception
     */
    protected function mockSyncWoocommerceShopInfo(string $imageCdnPrefix): void
    {
        // Mock SyncWoocommerceShopInfo
        StoreKeeperApi::$mockAdapter->withModule(
            'ShopModule',
            function (MockInterface $module) use ($imageCdnPrefix) {
                $module->shouldReceive('getShopWithRelation')->andReturnUsing(
                    function ($got) use ($imageCdnPrefix) {
                        return [
                            StoreKeeperOptions::IMAGE_CDN_PREFIX => $imageCdnPrefix,
                        ];
                    }
                );
            }
        );

        StoreKeeperApi::$mockAdapter->withModule(
            'ShopModule',
            function (MockInterface $module) {
                $module->shouldReceive('listConfigurations')->andReturnUsing(
                    function ($got) {
                        return [
                            'data' => [
                                ['currency_iso3' => 'EUR'],
                            ],
                        ];
                    }
                );
            }
        );
    }

    protected function prepareVFSForCDNImageTest(string $imageCdnPrefix): void
    {
        // Prepare VFS for CDN image test
        $rootDirectoryName = 'test-shop.sk-cdn.net';
        $testImageContent = file_get_contents($this->getDataDir().self::DATADUMP_DIRECTORY.'/media/'.self::MEDIA_IMAGE_JPEG_FILE);
        $testCatSampleImageContent = file_get_contents($this->getDataDir().self::DATADUMP_DIRECTORY.'/media/'.self::MEDIA_CAT_SAMPLE_IMAGE_JPEG_FILE);

        $structure = [
            'g' => [
                'test-shop-img-scale' => [
                    $imageCdnPrefix.'.'.Media::FULL_VARIANT_KEY => [
                        'f' => [
                            'image.jpeg' => $testImageContent,
                            'cat_sample.jpg' => $testCatSampleImageContent,
                        ],
                    ],
                ],
            ],
        ];

        vfsStream::setup($rootDirectoryName);
        vfsStream::create($structure);
    }
}
