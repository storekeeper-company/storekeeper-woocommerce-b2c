<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceCategories;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceTags;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\UnitTest\AbstractProductTest;
use Throwable;
use WC_Helper_Product;
use WC_Product;

class ProductHandlerTest extends AbstractProductTest
{
    const MEDIA_DATADUMP_DIRECTORY = 'events/products/media';

    const CREATE_DATADUMP_DIRECTORY = 'events/products/createProduct';
    const CREATE_DATADUMP_HOOK = 'events/hook.events.createProduct.json';
    const CREATE_DATADUMP_PRODUCT = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.bd9e4c8829238df3a0f78246f8df8690ca1c8cdd31bcb1b44a19132c10feee96.json';

    const CREATE_TAG_DATADUMP_DIRECTORY = 'events/products/createTag';
    const CREATE_TAG_DATADUMP_SOURCE_FILE = 'moduleFunction.ShopModule::listTranslatedCategoryForHooks.success.json';

    const CREATE_CATEGORY_DATADUMP_DIRECTORY = 'events/products/createCategory';
    const CREATE_CATEGORY_DATADUMP_SOURCE_FILE = 'moduleFunction.ShopModule::listTranslatedCategoryForHooks.success.json';

    const UPDATE_DATADUMP_DIRECTORY = 'events/products/updateProduct';
    const UPDATE_DATADUMP_HOOK = 'events/hook.events.updateProduct.json';
    const UPDATE_PRICES_DATADUMP_HOOK = 'events/hook.events.updateProductPrices.json';
    const UPDATE_STOCK_DATADUMP_HOOK = 'events/hook.events.updateProductStock.json';
    const UPDATE_CROSS_SELL_DATADUMP_HOOK = 'events/hook.events.updateProductCrossSell.json';
    const UPDATE_UP_SELL_DATADUMP_HOOK = 'events/hook.events.updateProductUpSell.json';
    const UPDATE_DATADUMP_PRODUCT = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.bd9e4c8829238df3a0f78246f8df8690ca1c8cdd31bcb1b44a19132c10feee96.json';

    const DEACTIVATE_DATADUMP_DIRECTORY = 'events/products/deactivateProduct';
    const DEACTIVATE_DATADUMP_HOOK = 'events/hook.events.deactivateProduct.json';
    const DEACTIVATE_DATADUMP_PRODUCT = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.bd9e4c8829238df3a0f78246f8df8690ca1c8cdd31bcb1b44a19132c10feee96.json';

    const ACTIVATE_DATADUMP_DIRECTORY = 'events/products/activateProduct';
    const ACTIVATE_DATADUMP_HOOK = 'events/hook.events.activateProduct.json';
    const ACTIVATE_DATADUMP_PRODUCT = 'moduleFunction.ShopModule::naturalSearchShopFlatProductForHooks.bd9e4c8829238df3a0f78246f8df8690ca1c8cdd31bcb1b44a19132c10feee96.json';

    const POST_STATUS_TRASHED = 'trash';

    public function testCreateProductWithCategoriesAndTags()
    {
        $this->mockCreateCategories();
        $this->mockCreateTags();
        $woocommerceCreatedProduct = $this->mockCreateProductRequestWithTest();

        // Get the updated data dump as a dotnotated collection
        $originalProductFile = $this->getDataDump(self::CREATE_DATADUMP_DIRECTORY.'/'.self::CREATE_DATADUMP_PRODUCT);
        $originalProduct = $originalProductFile->getReturn()['data'];
        $originalProductData = new Dot($originalProduct[0]);
        $originalProductCategories = $originalProductData->get('flat_product.categories');

        $expectedCategoryIds = [];
        $expectedTagIds = [];

        foreach ($originalProductCategories as $originalProductCategory) {
            $categoryType = $originalProductCategory['category_type'];
            if (ProductImport::CATEGORY_TAG_MODULE === $categoryType['module_name']) {
                if (ProductImport::TAG_ALIAS === $categoryType['alias']) {
                    // Label/Tag
                    $expectedTagIds[] = $originalProductCategory['id'];
                } elseif (ProductImport::CATEGORY_ALIAS === $categoryType['alias']) {
                    // Category
                    $expectedCategoryIds[] = $originalProductCategory['id'];
                }
            }
        }

        $productCategoryIds = $woocommerceCreatedProduct->get_category_ids();
        $productTagIds = $woocommerceCreatedProduct->get_tag_ids();

        $actualCategoryIds = [];
        foreach ($productCategoryIds as $productCategoryId) {
            $categoryTerm = get_term($productCategoryId, 'product_cat');
            $actualCategoryIds[] = get_term_meta($categoryTerm->term_id, 'storekeeper_id', true);
        }

        $actualTagIds = [];
        foreach ($productTagIds as $productTagId) {
            $tagTerm = get_term($productTagId, 'product_tag');
            $actualTagIds[] = get_term_meta($tagTerm->term_id, 'storekeeper_id', true);
        }

        $this->assertEqualSets($expectedCategoryIds, $actualCategoryIds);
        $this->assertEqualSets($expectedTagIds, $actualTagIds);

        $this->assertProduct($originalProductData, $woocommerceCreatedProduct);
    }

    public function testCreateProduct()
    {
        $wc_created_product = $this->mockCreateProductRequestWithTest();

        // Get the updated data dump as a dotnotated collection
        $create_file = $this->getDataDump(self::CREATE_DATADUMP_DIRECTORY.'/'.self::CREATE_DATADUMP_PRODUCT);
        $created_product_data = $create_file->getReturn()['data'];
        $created_product_data = new Dot($created_product_data[0]);

        // Compare the values of the updated data dump against the updated wordpress product
        $this->assertProduct($created_product_data, $wc_created_product);

        $this->assertTaskNotCount(0, 'There are not supposed to be any tasks left to process');
    }

    public function testUpdateProductStockOnly()
    {
        $woocommerceCreatedProduct = $this->mockCreateProductRequestWithTest();
        $expectedSku = $woocommerceCreatedProduct->get_sku();
        $expectedGalleryImages = $woocommerceCreatedProduct->get_gallery_image_ids();
        $expectedRegularPrice = $woocommerceCreatedProduct->get_regular_price();
        $expectedSalePrice = $woocommerceCreatedProduct->get_sale_price();
        $woocommerceUpdatedProduct = $this->mockUpdateProductRequestWithTest(self::UPDATE_STOCK_DATADUMP_HOOK);

        // Get the updated data dump as a dotnotated collection
        $updatedFile = $this->getDataDump(self::UPDATE_DATADUMP_DIRECTORY.'/'.self::UPDATE_DATADUMP_PRODUCT);
        $updatedProductData = $updatedFile->getReturn()['data'];
        $updatedProductData = new Dot($updatedProductData[0]);

        $this->assertTaskNotCount(0, 'There are not supposed to be any tasks left to process');

        // Other details like SKU and images should not be updated
        $sku = $woocommerceUpdatedProduct->get_sku();
        $this->assertNotEquals($updatedProductData['sku'], $sku, 'Product\'s SKU should not be updated');
        $this->assertEquals($expectedSku, $sku, 'Product\'s SKU be the same as when it was created');

        $galleryImages = $woocommerceUpdatedProduct->get_gallery_image_ids();
        $productImages = $updatedProductData->get('flat_product.product_images');
        foreach ($productImages as $index => $images) {
            if ($images['id'] === $updatedProductData->get('flat_product.main_image.id')) {
                unset($productImages[$index]);
            }
        }
        $this->assertNotSameSize($galleryImages, $productImages, 'Product images should not be updated');
        $this->assertEquals($expectedGalleryImages, $galleryImages, 'Product images should be same as when it was created');

        // Regular price should not be updated
        if (self::WC_TYPE_CONFIGURABLE !== $woocommerceUpdatedProduct->get_type()) {
            $updatedRegularPrice = $updatedProductData->get('product_default_price.ppu_wt');
            $this->assertNotEquals(
                $updatedRegularPrice,
                $woocommerceUpdatedProduct->get_regular_price(),
                "[sku=$sku] WooCommerce regular price should not match expected regular price"
            );
            $this->assertEquals($expectedRegularPrice, $woocommerceUpdatedProduct->get_regular_price(), 'Product regular prices should be the same when it was created');

            // Discounted price should not be updated
            if ($updatedProductData->get('product_price.ppu_wt') !== $updatedRegularPrice) {
                $updatedDiscountedPrice = $updatedProductData->get('product_price.ppu_wt');
                $this->assertNotEquals(
                    $updatedDiscountedPrice,
                    $woocommerceUpdatedProduct->get_sale_price(),
                    "[sku=$sku] WooCommerce discount price should not match the expected discount price"
                );
                $this->assertEquals($expectedSalePrice, $woocommerceUpdatedProduct->get_sale_price(), 'Product sale prices should be the same when it was created');
            }
        }

        // No cross-sell products should be set
        $crossSellIds = $woocommerceUpdatedProduct->get_cross_sell_ids();
        $this->assertEmpty($crossSellIds, 'Cross-sell products should not be set');

        // No up-sell products should be set
        $upSellIds = $woocommerceUpdatedProduct->get_upsell_ids();
        $this->assertEmpty($upSellIds, 'Up-sell products should not be set');

        $this->assertProductStock($updatedProductData, $woocommerceUpdatedProduct, $sku);
    }

    public function testUpdateProductPricesOnly()
    {
        $woocommerceCreatedProduct = $this->mockCreateProductRequestWithTest();
        $expectedSku = $woocommerceCreatedProduct->get_sku();
        $expectedGalleryImages = $woocommerceCreatedProduct->get_gallery_image_ids();
        $expectedStockQuantity = $woocommerceCreatedProduct->get_stock_quantity();
        $woocommerceUpdatedProduct = $this->mockUpdateProductRequestWithTest(self::UPDATE_PRICES_DATADUMP_HOOK);

        // Get the updated data dump as a dotnotated collection
        $updatedFile = $this->getDataDump(self::UPDATE_DATADUMP_DIRECTORY.'/'.self::UPDATE_DATADUMP_PRODUCT);
        $updatedProductData = $updatedFile->getReturn()['data'];
        $updatedProductData = new Dot($updatedProductData[0]);

        $this->assertTaskNotCount(0, 'There are not supposed to be any tasks left to process');

        // Other details like SKU and images should not be updated
        $sku = $woocommerceUpdatedProduct->get_sku();
        $this->assertNotEquals($updatedProductData['sku'], $sku, 'Product\'s SKU should not be updated');
        $this->assertEquals($expectedSku, $sku, 'Product\'s SKU be the same as when it was created');

        $galleryImages = $woocommerceUpdatedProduct->get_gallery_image_ids();
        $productImages = $updatedProductData->get('flat_product.product_images');
        foreach ($productImages as $index => $images) {
            if ($images['id'] === $updatedProductData->get('flat_product.main_image.id')) {
                unset($productImages[$index]);
            }
        }
        $this->assertNotSameSize($galleryImages, $productImages, 'Product images should not be updated');
        $this->assertEquals($expectedGalleryImages, $galleryImages, 'Product images should be same as when it was created');

        // Stock quantity should not be updated
        $updatedStockQuantity = $updatedProductData->get('flat_product.product.product_stock.value');
        $this->assertNotEquals(
            $updatedStockQuantity,
            $woocommerceUpdatedProduct->get_stock_quantity(),
            "[sku=$sku] WooCommerce stock quantity should not match"
        );
        $this->assertEquals($expectedStockQuantity, $woocommerceUpdatedProduct->get_stock_quantity(), 'Product stock quantity should be same as when it was created');

        // No cross-sell products should be set
        $crossSellIds = $woocommerceUpdatedProduct->get_cross_sell_ids();
        $this->assertEmpty($crossSellIds, 'Cross-sell products should not be set');

        // No up-sell products should be set
        $upSellIds = $woocommerceUpdatedProduct->get_upsell_ids();
        $this->assertEmpty($upSellIds, 'Up-sell products should not be set');

        $this->assertProductPrices($updatedProductData, $woocommerceUpdatedProduct, $sku);
    }

    public function testUpdateProductCrossSellOnly()
    {
        $woocommerceCreatedProduct = $this->mockCreateProductRequestWithTest();
        $expectedSku = $woocommerceCreatedProduct->get_sku();
        $expectedGalleryImages = $woocommerceCreatedProduct->get_gallery_image_ids();
        $expectedStockQuantity = $woocommerceCreatedProduct->get_stock_quantity();
        $expectedRegularPrice = $woocommerceCreatedProduct->get_regular_price();
        $expectedSalePrice = $woocommerceCreatedProduct->get_sale_price();
        $woocommerceUpdatedProduct = $this->mockUpdateProductRequestWithTest(self::UPDATE_CROSS_SELL_DATADUMP_HOOK);

        // Get the updated data dump as a dotnotated collection
        $updatedFile = $this->getDataDump(self::UPDATE_DATADUMP_DIRECTORY.'/'.self::UPDATE_DATADUMP_PRODUCT);
        $updatedProductData = $updatedFile->getReturn()['data'];
        $updatedProductData = new Dot($updatedProductData[0]);

        $this->assertTaskNotCount(0, 'There are not supposed to be any tasks left to process');

        // Other details like SKU and images should not be updated
        $sku = $woocommerceUpdatedProduct->get_sku();
        $this->assertNotEquals($updatedProductData['sku'], $sku, 'Product\'s SKU should not be updated');
        $this->assertEquals($expectedSku, $sku, 'Product\'s SKU be the same as when it was created');

        $galleryImages = $woocommerceUpdatedProduct->get_gallery_image_ids();
        $productImages = $updatedProductData->get('flat_product.product_images');
        foreach ($productImages as $index => $images) {
            if ($images['id'] === $updatedProductData->get('flat_product.main_image.id')) {
                unset($productImages[$index]);
            }
        }
        $this->assertNotSameSize($galleryImages, $productImages, 'Product images should not be updated');
        $this->assertEquals($expectedGalleryImages, $galleryImages, 'Product images should be same as when it was created');

        // Stock quantity should not be updated
        $updatedStockQuantity = $updatedProductData->get('flat_product.product.product_stock.value');
        $this->assertNotEquals(
            $updatedStockQuantity,
            $woocommerceUpdatedProduct->get_stock_quantity(),
            "[sku=$sku] WooCommerce stock quantity should not match"
        );
        $this->assertEquals($expectedStockQuantity, $woocommerceUpdatedProduct->get_stock_quantity(), 'Product stock quantity should be same as when it was created');

        // Regular price should not be updated
        if (self::WC_TYPE_CONFIGURABLE !== $woocommerceUpdatedProduct->get_type()) {
            $updatedRegularPrice = $updatedProductData->get('product_default_price.ppu_wt');
            $this->assertNotEquals(
                $updatedRegularPrice,
                $woocommerceUpdatedProduct->get_regular_price(),
                "[sku=$sku] WooCommerce regular price should not match expected regular price"
            );
            $this->assertEquals($expectedRegularPrice, $woocommerceUpdatedProduct->get_regular_price(), 'Product regular prices should be the same when it was created');

            // Discounted price should not be updated
            if ($updatedProductData->get('product_price.ppu_wt') !== $updatedRegularPrice) {
                $updatedDiscountedPrice = $updatedProductData->get('product_price.ppu_wt');
                $this->assertNotEquals(
                    $updatedDiscountedPrice,
                    $woocommerceUpdatedProduct->get_sale_price(),
                    "[sku=$sku] WooCommerce discount price should not match the expected discount price"
                );
                $this->assertEquals($expectedSalePrice, $woocommerceUpdatedProduct->get_sale_price(), 'Product sale prices should be the same when it was created');
            }
        }

        // No up-sell products should be set
        $upSellIds = $woocommerceUpdatedProduct->get_upsell_ids();
        $this->assertEmpty($upSellIds, 'Up-sell products should not be set');

        $this->assertProductCrossSell($woocommerceUpdatedProduct, $sku);
    }

    public function testUpdateProductUpsellOnly()
    {
        $woocommerceCreatedProduct = $this->mockCreateProductRequestWithTest();
        $expectedSku = $woocommerceCreatedProduct->get_sku();
        $expectedGalleryImages = $woocommerceCreatedProduct->get_gallery_image_ids();
        $expectedStockQuantity = $woocommerceCreatedProduct->get_stock_quantity();
        $expectedRegularPrice = $woocommerceCreatedProduct->get_regular_price();
        $expectedSalePrice = $woocommerceCreatedProduct->get_sale_price();
        $woocommerceUpdatedProduct = $this->mockUpdateProductRequestWithTest(self::UPDATE_UP_SELL_DATADUMP_HOOK);

        // Get the updated data dump as a dotnotated collection
        $updatedFile = $this->getDataDump(self::UPDATE_DATADUMP_DIRECTORY.'/'.self::UPDATE_DATADUMP_PRODUCT);
        $updatedProductData = $updatedFile->getReturn()['data'];
        $updatedProductData = new Dot($updatedProductData[0]);

        $this->assertTaskNotCount(0, 'There are not supposed to be any tasks left to process');

        // Other details like SKU and images should not be updated
        $sku = $woocommerceUpdatedProduct->get_sku();
        $this->assertNotEquals($updatedProductData['sku'], $sku, 'Product\'s SKU should not be updated');
        $this->assertEquals($expectedSku, $sku, 'Product\'s SKU be the same as when it was created');

        $galleryImages = $woocommerceUpdatedProduct->get_gallery_image_ids();
        $productImages = $updatedProductData->get('flat_product.product_images');
        foreach ($productImages as $index => $images) {
            if ($images['id'] === $updatedProductData->get('flat_product.main_image.id')) {
                unset($productImages[$index]);
            }
        }
        $this->assertNotSameSize($galleryImages, $productImages, 'Product images should not be updated');
        $this->assertEquals($expectedGalleryImages, $galleryImages, 'Product images should be same as when it was created');

        // Stock quantity should not be updated
        $updatedStockQuantity = $updatedProductData->get('flat_product.product.product_stock.value');
        $this->assertNotEquals(
            $updatedStockQuantity,
            $woocommerceUpdatedProduct->get_stock_quantity(),
            "[sku=$sku] WooCommerce stock quantity should not match"
        );
        $this->assertEquals($expectedStockQuantity, $woocommerceUpdatedProduct->get_stock_quantity(), 'Product stock quantity should be same as when it was created');

        // Regular price should not be updated
        if (self::WC_TYPE_CONFIGURABLE !== $woocommerceUpdatedProduct->get_type()) {
            $updatedRegularPrice = $updatedProductData->get('product_default_price.ppu_wt');
            $this->assertNotEquals(
                $updatedRegularPrice,
                $woocommerceUpdatedProduct->get_regular_price(),
                "[sku=$sku] WooCommerce regular price should not match expected regular price"
            );
            $this->assertEquals($expectedRegularPrice, $woocommerceUpdatedProduct->get_regular_price(), 'Product regular prices should be the same when it was created');

            // Discounted price should not be updated
            if ($updatedProductData->get('product_price.ppu_wt') !== $updatedRegularPrice) {
                $updatedDiscountedPrice = $updatedProductData->get('product_price.ppu_wt');
                $this->assertNotEquals(
                    $updatedDiscountedPrice,
                    $woocommerceUpdatedProduct->get_sale_price(),
                    "[sku=$sku] WooCommerce discount price should not match the expected discount price"
                );
                $this->assertEquals($expectedSalePrice, $woocommerceUpdatedProduct->get_sale_price(), 'Product sale prices should be the same when it was created');
            }
        }

        // No cross-sell products should be set
        $crossSellIds = $woocommerceUpdatedProduct->get_cross_sell_ids();
        $this->assertEmpty($crossSellIds, 'Cross-sell products should not be set');

        $this->assertProductUpSell($woocommerceUpdatedProduct, $sku);
    }

    public function testUpdateProduct()
    {
        $this->mockCreateProductRequestWithTest();
        $woocommerceUpdatedProduct = $this->mockUpdateProductRequestWithTest();

        // Get the updated data dump as a dotnotated collection
        $updatedFile = $this->getDataDump(self::UPDATE_DATADUMP_DIRECTORY.'/'.self::UPDATE_DATADUMP_PRODUCT);
        $updatedProductData = $updatedFile->getReturn()['data'];
        $updatedProductData = new Dot($updatedProductData[0]);

        // Compare the values of the updated data dump against the updated wordpress product
        $this->assertProduct($updatedProductData, $woocommerceUpdatedProduct);

        $sku = $woocommerceUpdatedProduct->get_sku();
        $this->assertProductCrossSell($woocommerceUpdatedProduct, $sku);
        $this->assertProductUpSell($woocommerceUpdatedProduct, $sku);

        $this->assertTaskNotCount(0, 'There are not supposed to be any tasks left to process');
    }

    public function testOrderOnlySyncMode()
    {
        $this->assertProductCount(0, 'Environment not empty');
        $sku = 'MD826ZM/A2';
        $product = WC_Helper_Product::create_simple_product(false);
        $product->set_sku($sku);
        $product->set_stock_quantity(null);
        $product->set_manage_stock(false);
        $product->set_backorders('no');
        $product->set_stock_status(self::WC_STATUS_OUTOFSTOCK);
        $product->save();

        $expectedGalleryImages = $product->get_gallery_image_ids();
        $expectedRegularPrice = $product->get_regular_price();
        $expectedSalePrice = $product->get_sale_price();

        $this->handle_hook_request(
            self::UPDATE_DATADUMP_DIRECTORY,
            self::UPDATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events',
            false,
            StoreKeeperOptions::SYNC_MODE_ORDER_ONLY
        );

        $this->assertTaskNotCount(0, 'Should have created more than zero task');

        // Process all the tasks
        $this->runner->execute(ProcessAllTasks::getCommandName());

        // Get the updated data dump as a dotnotated collection
        $updatedFile = $this->getDataDump(self::UPDATE_DATADUMP_DIRECTORY.'/'.self::UPDATE_DATADUMP_PRODUCT);
        $updatedProductData = $updatedFile->getReturn()['data'];
        $updatedProductData = new Dot($updatedProductData[0]);

        // Get product again after task processing
        $woocommerceUpdatedProduct = wc_get_product($product);

        $galleryImages = $woocommerceUpdatedProduct->get_gallery_image_ids();
        $productImages = $updatedProductData->get('flat_product.product_images');
        foreach ($productImages as $index => $images) {
            if ($images['id'] === $updatedProductData->get('flat_product.main_image.id')) {
                unset($productImages[$index]);
            }
        }
        $this->assertNotSameSize($galleryImages, $productImages, 'Product images should not be updated');
        $this->assertEquals($expectedGalleryImages, $galleryImages, 'Product images should be same as when it was created');

        // Regular price should not be updated
        if (self::WC_TYPE_CONFIGURABLE !== $woocommerceUpdatedProduct->get_type()) {
            $updatedRegularPrice = $updatedProductData->get('product_default_price.ppu_wt');
            $this->assertNotEquals(
                $updatedRegularPrice,
                $woocommerceUpdatedProduct->get_regular_price(),
                "[sku=$sku] WooCommerce regular price should not match expected regular price"
            );
            $this->assertEquals($expectedRegularPrice, $woocommerceUpdatedProduct->get_regular_price(), 'Product regular prices should be the same when it was created');

            // Discounted price should not be updated
            if ($updatedProductData->get('product_price.ppu_wt') !== $updatedRegularPrice) {
                $updatedDiscountedPrice = $updatedProductData->get('product_price.ppu_wt');
                $this->assertNotEquals(
                    $updatedDiscountedPrice,
                    $woocommerceUpdatedProduct->get_sale_price(),
                    "[sku=$sku] WooCommerce discount price should not match the expected discount price"
                );
                $this->assertEquals($expectedSalePrice, $woocommerceUpdatedProduct->get_sale_price(), 'Product sale prices should be the same when it was created');
            }
        }

        $this->assertNotNull($woocommerceUpdatedProduct->get_stock_quantity(), 'Stock quantity was not updated.');
        $this->assertTrue($woocommerceUpdatedProduct->get_manage_stock(), 'Stock manage was not updated.');
        $this->assertEquals('yes', $woocommerceUpdatedProduct->get_backorders(), 'Stock backorders was not updated.');
        $this->assertEquals(self::WC_STATUS_INSTOCK, $woocommerceUpdatedProduct->get_stock_status(), 'Stock manage was not updated.');

        $this->assertProductStock($updatedProductData, $woocommerceUpdatedProduct, $sku);
    }

    private function assertProductCount(int $expected, string $message)
    {
        $this->assertCount(
            $expected,
            wc_get_products(
                [
                    'post_type' => 'product',
                ]
            ),
            $message
        );
    }

    public function testDeactivateProduct()
    {
        // Handle the product creation hook event so there is a product to deactivate
        $product = $this->mockCreateProductRequestWithTest();

        $original_post_id = $product->get_id();

        // Handle the product deactivation hook
        $deactivation_options = $this->handle_hook_request(
            self::DEACTIVATE_DATADUMP_DIRECTORY,
            self::DEACTIVATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
        );

        // Try to retrieve the product from wordpress. Should return an empty array since the product has been removed
        $wc_products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $deactivation_options['id'],
            ]
        );
        $this->assertEmpty(
            $wc_products,
            'Product with the StoreKeeper id could still be retrieved from WooCommerce'
        );

        // Make sure that the product (post) is actually trashed
        $original_post_status = get_post_status($original_post_id);
        $this->assertEquals(
            self::POST_STATUS_TRASHED,
            $original_post_status,
            'WooCommerce post status doesn\'t match the expected post status'
        );

        $this->assertTaskNotCount(0, 'There are not supposed to be any tasks left to process');
    }

    public function testActivateProductNoInitial()
    {
        // Handle the product activation hook event
        $activation_options = $this->handle_hook_request(
            self::ACTIVATE_DATADUMP_DIRECTORY,
            self::ACTIVATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
        );
        // Fetch the product that was created by the activation hook
        $wc_products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $activation_options['id'],
            ]
        );
        $this->assertCount(
            1,
            $wc_products,
            'Actual size of the retrieved product collection is not equal to one'
        );

        $wc_created_product = $wc_products[0];

        // Get the data dump
        $activate_file = $this->getDataDump(self::ACTIVATE_DATADUMP_DIRECTORY.'/'.self::ACTIVATE_DATADUMP_PRODUCT);
        $activated_product_data = $activate_file->getReturn()['data'];
        $activated_product_data = new Dot($activated_product_data[0]);

        // Compare the values of the updated data dump against the updated wordpress product
        $this->assertProduct($activated_product_data, $wc_created_product);

        $this->assertTaskNotCount(0, 'There are not supposed to be any tasks left to process');
    }

    public function testActivateProductAfterDeactivating()
    {
        // see : https://app.clickup.com/t/3cp0b5?comment=38147526

        // Handle the product creation hook event so there is a product to deactivate
        $this->mockCreateProductRequestWithTest();

        // Handle the product deactivation hook
        $deactivation_options = $this->handle_hook_request(
            self::DEACTIVATE_DATADUMP_DIRECTORY,
            self::DEACTIVATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
        );

        // Try to retrieve the product from wordpress. Should return an empty array since the product has been removed
        $wc_products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $deactivation_options['id'],
            ]
        );
        $this->assertEmpty(
            $wc_products,
            'Product with the StoreKeeper id could still be retrieved from WooCommerce'
        );

        // Handle the product activation hook event
        $activation_options = $this->handle_hook_request(
            self::ACTIVATE_DATADUMP_DIRECTORY,
            self::ACTIVATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
        );
        // Fetch the product that was created by the activation hook
        $wc_products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $activation_options['id'],
            ]
        );
        $this->assertCount(
            1,
            $wc_products,
            'Actual size of the retrieved product collection is not equal to one'
        );
        $wc_created_product = $wc_products[0];

        // Get the data dump
        $activate_file = $this->getDataDump(self::ACTIVATE_DATADUMP_DIRECTORY.'/'.self::ACTIVATE_DATADUMP_PRODUCT);
        $activated_product_data = $activate_file->getReturn()['data'];
        $activated_product_data = new Dot($activated_product_data[0]);

        // Compare the values of the updated data dump against the updated wordpress product
        $this->assertProduct($activated_product_data, $wc_created_product);

        $this->assertTaskNotCount(0, 'There are not supposed to be any tasks left to process');
    }

    /**
     * @throws Throwable
     */
    protected function handle_hook_request(
        string $datadump_dir,
        string $datadump_file,
        string $expected_backref,
        string $expected_hook_action,
        bool $process_tasks = true,
        string $syncMode = StoreKeeperOptions::SYNC_MODE_FULL_SYNC
    ): array {
        // Initialize the connection with the API
        $this->initApiConnection($syncMode);

        // Setup the data dump
        $this->mockApiCallsFromDirectory($datadump_dir, true);
        $this->mockMediaFromDirectory(self::MEDIA_DATADUMP_DIRECTORY);
        $file = $this->getHookDataDump($datadump_file);

        // Check the backref of the product event
        $backref = $file->getEventBackref();
        list($main_type, $original_options) = StoreKeeperApi::extractMainTypeAndOptions($backref);
        $this->assertEquals(
            $expected_backref,
            $main_type,
            'WooCommerce event backref doesn\'t match the expected event backref'
        );

        // Check the hook action from the file
        $this->assertEquals(
            $expected_hook_action,
            $file->getHookAction(),
            'WooCommerce hook action doesn\'t match the expected hook action'
        );

        // Handle the request
        $rest = $this->getRestWithToken($file);
        $response = $this->handleRequest($rest);
        $response = $response->get_data();
        $this->assertTrue($response['success'], 'Request failed');

        if ($process_tasks) {
            $this->runner->execute(ProcessAllTasks::getCommandName());
        }

        return $original_options;
    }

    protected function mockCreateProductRequestWithTest(): WC_Product
    {
        $this->mockApiCallsFromCommonDirectory();
        // Handle the product creation hook event
        $creationOptions = $this->handle_hook_request(
            self::CREATE_DATADUMP_DIRECTORY,
            self::CREATE_DATADUMP_HOOK,
            'ShopModule::ShopProduct',
            'events'
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
            1,
            $products,
            'Actual size of the retrieved product collection is not equal to one'
        );

        return $products[0];
    }

    protected function mockUpdateProductRequestWithTest(string $dataDumpHook = self::UPDATE_DATADUMP_HOOK): WC_Product
    {
        // Handle the product update hook event
        $updateOptions = $this->handle_hook_request(
            self::UPDATE_DATADUMP_DIRECTORY,
            $dataDumpHook,
            'ShopModule::ShopProduct',
            'events'
        );
        // Fetch the product that was created by the update hook
        $products = wc_get_products(
            [
                'post_type' => 'product',
                'meta_key' => 'storekeeper_id',
                'meta_value' => $updateOptions['id'],
            ]
        );
        $this->assertCount(
            1,
            $products,
            'Actual size of the retrieved product collection is not equal to one'
        );

        return $products[0];
    }

    public function mockCreateTags()
    {
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::CREATE_TAG_DATADUMP_DIRECTORY);

        $file = $this->getDataDump(self::CREATE_TAG_DATADUMP_DIRECTORY.'/'.self::CREATE_CATEGORY_DATADUMP_SOURCE_FILE);
        $tags = $file->getReturn()['data'];

        // Run the tag import command
        $this->runner->execute(SyncWoocommerceTags::getCommandName());

        return $tags;
    }

    public function mockCreateCategories()
    {
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::CREATE_CATEGORY_DATADUMP_DIRECTORY);

        $file = $this->getDataDump(self::CREATE_CATEGORY_DATADUMP_DIRECTORY.'/'.self::CREATE_CATEGORY_DATADUMP_SOURCE_FILE);
        $categories = $file->getReturn()['data'];

        // Run the category import command
        $this->runner->execute(SyncWoocommerceCategories::getCommandName());

        return $categories;
    }
}
