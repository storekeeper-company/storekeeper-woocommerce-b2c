<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use Exception;
use StoreKeeper\WooCommerce\B2C\Cache\StoreKeeperIdCache;
use StoreKeeper\WooCommerce\B2C\Exceptions\CannotFetchShopProductException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;
use StoreKeeper\WooCommerce\B2C\Tools\Categories;
use StoreKeeper\WooCommerce\B2C\Tools\Media;
use StoreKeeper\WooCommerce\B2C\Tools\ParseDown;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\Tools\WooCommerceAttributeMetadata;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use WC_Meta_Box_Product_Data;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;

class ProductImport extends AbstractProductImport
{
    protected $syncProductVariations = false;

    /**
     * @param $StoreKeeperId
     *
     * @return bool|\WP_Post
     *
     * @throws WordpressException
     */
    public static function getItem($StoreKeeperId)
    {
        $products = WordpressExceptionThrower::throwExceptionOnWpError(
            get_posts(
                [
                    'post_type' => 'product',
                    'number' => 1,
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $StoreKeeperId,
                    'suppress_filters' => false,
                    'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
                ]
            )
        );

        if (1 === count($products)) {
            return $products[0];
        }

        return false;
    }

    /**
     * @param $shop_product_id
     *
     * @return bool|\WP_Post
     *
     * @throws WordpressException
     */
    public static function findBackendShopProductId($shop_product_id)
    {
        $products = WordpressExceptionThrower::throwExceptionOnWpError(
            get_posts(
                [
                    'post_type' => ['product', 'product_variation'],
                    'number' => 1,
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $shop_product_id,
                    'suppress_filters' => false,
                    'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
                ]
            )
        );

        if (1 === count($products)) {
            return $products[0];
        }

        return false;
    }

    /**
     * @param $sku
     *
     * @return bool
     *
     * @throws WordpressException
     */
    public static function getAllItemsBySku($sku)
    {
        $products = WordpressExceptionThrower::throwExceptionOnWpError(
            get_posts(
                [
                    'post_type' => ['product', 'product_variation'],
                    'number' => 1,
                    'meta_key' => '_sku',
                    'meta_value' => $sku,
                    'suppress_filters' => false,
                    'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
                ]
            )
        );

        if (1 === count($products)) {
            return $products[0];
        }

        return false;
    }

    /**
     * @param Dot $dotObject
     *
     * @return bool|int
     *
     * @throws WordpressException
     * @throws \WC_Data_Exception
     */
    protected function processItem($dotObject, array $options = [])
    {
        $log_data = $this->setupLogData($dotObject);

        // If the product is dirty, it means it still needs processing to we plan another import
        if ($dotObject->get('flat_product.dirty')) {
            $this->debug('Product dirty, skipped', $log_data);
            TaskHandler::scheduleTask(
                TaskHandler::PRODUCT_IMPORT,
                $dotObject->get('id'),
                ['storekeeper_id' => (int) $dotObject->get('id')],
                true
            );
        } // Else we just process the task.
        else {
            $this->debug('Product clean, processing', $log_data);
            $importProductType = $dotObject->get('flat_product.product.type');

            $log_data['type'] = $importProductType;
            $this->debug('Product type set', $log_data);

            /* Check if the product type has changed, if so > trash the product. */
            if ($this->productTypeChanged($dotObject)) {
                // Product type changed, delete old product and add new one.
                $currentProduct = self::getProductByProductData($dotObject);

                if ($currentProduct) {
                    if ($currentProduct->post_parent) {
                        // Schedule a task to calculate the previous parent
                        $this->getTaskHandler()->rescheduleTask(
                            TaskHandler::PARENT_PRODUCT_RECALCULATION,
                            "post_id::{$currentProduct->post_parent}",
                            [
                                'parent_post_id' => $currentProduct->post_parent,
                            ]
                        );
                    }

                    // Product is being trashed because it was the easiest way of implementing type change withing WooCommerce.
                    // Else we need to check out how WooCommerce handles type changes
                    wp_trash_post($currentProduct->ID);
                }
            }

            /* Processing Assigned products */
            if ('configurable_assign' === $importProductType) {
                return $this->processAssignedProduct($dotObject, $log_data);
            }

            /* Processing configurable and simple */
            return $this->processSimpleAndConfigurableProduct($dotObject, $log_data, $options, $importProductType);
        }

        return false;
    }

    /**
     * @param Dot $productData
     *
     * @return bool|\WP_Post
     *
     * @throws WordpressException
     */
    public static function getProductByProductData($productData)
    {
        $productCheck = self::findBackendShopProductId($productData->get('id'));
        if (false === $productCheck && $productData->has('flat_product.product.sku')) {
            $productCheck = self::getAllItemsBySku($productData->get('flat_product.product.sku'));
        }

        return $productCheck;
    }

    private function productTypeChanged(Dot $productData)
    {
        $product = self::getProductByProductData($productData);
        if ($product) {
            $backendType = $productData->get('flat_product.product.type');
            $currentWordpressType = $product->post_type;

            $expectedWpType = 'product';
            if ('configurable_assign' === $backendType) {
                $expectedWpType = 'product_variation';
            }

            $changed = $currentWordpressType !== $expectedWpType;

            $this->debug(
                'Product type check.',
                [
                    'backend_type' => $backendType,
                    'wordpress_type' => $currentWordpressType,
                    'expected_wordpress_type' => $expectedWpType,
                    'changed' => $changed,
                ]
            );

            return $currentWordpressType !== $expectedWpType;
        }
        // product not found locally so it means it changed
        return true;
    }

    private function updatePostStatus($post_id, $status)
    {
        global $wpdb;
        $sql = <<<SQL
    UPDATE {$wpdb->prefix}posts
    SET post_status=%s
    WHERE ID=%d
SQL;
        $wpdb->query($wpdb->prepare($sql, $status, $post_id));
    }

    /**
     * @param $newProduct WC_Product_Simple|WC_Product_Variable
     * @param Dot $product
     *
     * @throws WordpressException
     */
    private function setCategories(&$newProduct, $product)
    {
        $categoryIds = [];

        if ($product->has('flat_product.categories')) {
            foreach ($product->get('flat_product.categories') as $category) {
                $go_category = Categories::getCategoryById($category['id'], $category['slug']);
                if (false !== $go_category) {
                    $categoryIds[] = $go_category->term_id;
                }
            }
        }

        $newProduct->set_category_ids($categoryIds);
    }

    /**
     * @param $newProduct WC_Product_Simple|WC_Product_Variable
     * @param Dot $product
     *
     * @throws WordpressException
     */
    private function setTags(&$newProduct, $product)
    {
        $tagIds = [];

        if ($product->has('flat_product.categories')) {
            foreach ($product->get('flat_product.categories') as $tag) {
                $go_tag = $this->getLabel($tag['id']);
                if (false !== $go_tag) {
                    $tagIds[] = $go_tag->term_id;
                }
            }
        }

        $newProduct->set_tag_ids($tagIds);
    }

    /**
     * @param $StoreKeeperId
     *
     * @return bool \WP_Term
     *
     * @throws WordpressException
     */
    private function getLabel($StoreKeeperId)
    {
        $labels = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => 'product_tag',
                    'hide_empty' => false,
                    'number' => 1,
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $StoreKeeperId,
                ]
            )
        );

        if (1 === count($labels)) {
            return $labels[0];
        }

        return false;
    }

    /**
     * @param $newProduct
     * @param $product
     *
     * @return mixed
     */
    private function setImage(&$newProduct, $product)
    {
        if ($product->has('flat_product.main_image')) {
            $attachment_id = Media::createAttachment($product->get('flat_product.main_image.big_url'));
            $this->debug(
                'Found main product image',
                [
                    'product_images' => $product->get('flat_product.main_image'),
                    'attachment_id' => $attachment_id,
                ]
            );
            $newProduct->set_image_id($attachment_id);

            return $product->get('flat_product.main_image.id');
        }
    }

    /**
     * @param $newProduct WC_Product_Simple|WC_Product_Variable
     * @param $product
     * @param null $main_image_id
     */
    private function setGalleryImages(&$newProduct, $product, $main_image_id = null)
    {
        $attachment_ids = [];
        $count = 0;
        if ($product->has('flat_product.product_images')) {
            $count = count($product->get('flat_product.product_images'));
            foreach ($product->get('flat_product.product_images') as $product_image) {
                if ($product_image['id'] !== $main_image_id) {
                    $attachment_ids[] = Media::createAttachment($product_image['big_url']);
                }
            }
        }
        $this->debug(
            "Found $count product images",
            [
                'product_images' => $product->get('flat_product.product_images'),
                'attachment_ids' => $attachment_ids,
            ]
        );

        $newProduct->set_gallery_image_ids($attachment_ids);
    }

    /**
     * @param WC_Product_Simple|WC_Product_Variable $newProduct
     * @param Dot                                   $product
     *
     * @return array
     *
     * @throws WordpressException
     * @throws \WC_Data_Exception
     */
    protected function setUpsellIds(&$newProduct, $product)
    {
        $this->debug('[upsell] Processing');
        $shop_product_id = $product->get('id');
        $upsell_ids = [];
        $ShopModule = $this->storekeeper_api->getModule('ShopModule');
        $language = $this->getLanguage();
        $shop_product_ids_to_fetch_later = [];

        $upsell_shop_product_ids = $ShopModule->getUpsellShopProductIds($shop_product_id);
        $this->debug('[upsell] Fetched upsell id\'s', $upsell_shop_product_ids);

        foreach ($upsell_shop_product_ids as $shop_product_id) {
            // Checking is we can find the product by sku
            $product = self::findBackendShopProductId($shop_product_id);
            if (false !== $product) {
                $this->debug('[upsell] Found product with id'.$product->ID, $product);
                $upsell_ids[] = $product->ID;
            } else {
                $shop_product_ids_to_fetch_later[] = $shop_product_id;
            }
        }
        $this->debug('[upsell] Did not find following shop_product_ids', $shop_product_ids_to_fetch_later);

        if (count($shop_product_ids_to_fetch_later) > 0) {
            $response = $ShopModule->naturalSearchShopFlatProductForHooks(
                0,
                $language,
                0,
                0,
                null,
                [
                    [
                        'name' => 'id__in_list',
                        'multi_val' => $shop_product_ids_to_fetch_later,
                    ],
                ]
            );
            $data = $response['data'];

            $this->debug('[upsell] Fetched products to process, amount: '.count($data), $data);

            foreach ($data as $productData) {
                $productFound = false;
                $product = new Dot($productData);
                $this->debug('[upsell] processing product with id', $product->get('id'));

                // Check if the product already exists.
                $productCheck = self::findBackendShopProductId($product->get('id'));
                if (false !== $productCheck) {
                    $this->debug('[UPSELL] product found', $productCheck->ID);
                    $upsell_ids[] = $productCheck->ID;
                    $productFound = true;
                } else {
                    if ($product->has('flat_product.product.sku')) {
                        $productCheck = self::getAllItemsBySku($product->get('flat_product.product.sku'));
                        if (false !== $productCheck) {
                            $this->debug('[UPSELL] product found', $productCheck->ID);
                            $upsell_ids[] = $productCheck->ID;
                            $productFound = true;
                        }
                    }
                }

                if (!$productFound) {
                    $this->debug('[upsell] Process product', $productData);
                    $this->processItem(
                        $product,
                        [
                            'skip_cross_sell' => true,
                            'skip_upsell' => true,
                        ]
                    );
                    $this->debug('[upsell] Processed product');

                    // Check AGAIN if the product already exists.
                    $productCheck = self::findBackendShopProductId($product->get('id'));
                    if (false !== $productCheck) {
                        $this->debug('[UPSELL] product found after search', $productCheck->ID);
                        $upsell_ids[] = $productCheck->ID;
                    } else {
                        if ($product->has('flat_product.product.sku')) {
                            $productCheck = self::getAllItemsBySku($product->get('flat_product.product.sku'));
                            if (false !== $productCheck) {
                                $this->debug('[UPSELL] product found after search', $productCheck->ID);
                                $upsell_ids[] = $productCheck->ID;
                            }
                        }
                    }
                }
            }
        }

        $newProduct->set_upsell_ids($upsell_ids);

        return $upsell_ids;
    }

    /**
     * @param WC_Product_Simple|WC_Product_Variable $newProduct
     * @param Dot                                   $product
     *
     * @return array
     */
    protected function setCrossSellIds(&$newProduct, $product)
    {
        $this->debug('[cross sell] Processing');
        $shop_product_id = $product->get('id');
        $cross_sell_ids = [];
        $ShopModule = $this->storekeeper_api->getModule('ShopModule');
        $language = $this->getLanguage();
        $shop_product_ids_to_fetch_later = [];

        $cross_sell_shop_product_ids = $ShopModule->getCrossSellShopProductIds((int) $shop_product_id);
        $this->debug('[cross_sell] Fetched cross_sell id\'s', $cross_sell_shop_product_ids);

        foreach ($cross_sell_shop_product_ids as $shop_product_id) {
            // Checking is we can find the product by sku
            $product = self::findBackendShopProductId($shop_product_id);
            if (false !== $product) {
                $this->debug('[cross_sell] Found product with id'.$product->ID, $product);
                $cross_sell_ids[] = $product->ID;
            } else {
                $shop_product_ids_to_fetch_later[] = $shop_product_id;
            }
        }
        $this->debug('[cross_sell] Did not find following shop_product_ids', $shop_product_ids_to_fetch_later);

        if (count($shop_product_ids_to_fetch_later) > 0) {
            $response = $ShopModule->naturalSearchShopFlatProductForHooks(
                0,
                $language,
                0,
                0,
                null,
                [
                    [
                        'name' => 'id__in_list',
                        'multi_val' => $shop_product_ids_to_fetch_later,
                    ],
                ]
            );
            $data = $response['data'];

            $this->debug('[cross_sell] Fetched products to process, amount: '.count($data), $data);

            foreach ($data as $productData) {
                $productFound = false;
                $product = new Dot($productData);
                $this->debug('[cross_sell] processing product with id', $product->get('id'));

                // Check if the product already exists.
                $productCheck = self::findBackendShopProductId($product->get('id'));
                if (false !== $productCheck) {
                    $this->debug('[cross_sell] product found', $productCheck->ID);
                    $cross_sell_ids[] = $productCheck->ID;
                    $productFound = true;
                } else {
                    if ($product->has('flat_product.product.sku')) {
                        $productCheck = self::getAllItemsBySku($product->get('flat_product.product.sku'));
                        if (false !== $productCheck) {
                            $this->debug('[cross_sell] product found', $productCheck->ID);
                            $cross_sell_ids[] = $productCheck->ID;
                            $productFound = true;
                        }
                    }
                }

                if (!$productFound) {
                    $this->debug('[cross_sell] Process product', $productData);
                    $this->processItem(
                        $product,
                        [
                            'skip_cross_sell' => true,
                            'skip_upsell' => true,
                        ]
                    );
                    $this->debug('[cross_sell] Processed product');

                    // Check AGAIN if the product already exists.
                    $productCheck = self::findBackendShopProductId($product->get('id'));
                    if (false !== $productCheck) {
                        $this->debug('[cross_sell] product found after search', $productCheck->ID);
                        $cross_sell_ids[] = $productCheck->ID;
                    } else {
                        if ($product->has('flat_product.product.sku')) {
                            $productCheck = self::getAllItemsBySku($product->get('flat_product.product.sku'));
                            if (false !== $productCheck) {
                                $this->debug('[cross_sell] product found after search', $productCheck->ID);
                                $cross_sell_ids[] = $productCheck->ID;
                            }
                        }
                    }
                }
            }
        }

        $newProduct->set_cross_sell_ids($cross_sell_ids);

        return $cross_sell_ids;
    }

    /**
     * @param $newProduct WC_Product_Simple|WC_Product_Variable
     * @param Dot $product
     *
     * @throws Exception
     */
    private function setSimpleAttributes(&$newProduct, $product)
    {
        $attribute_data = [];
        if ($product->has('flat_product.content_vars')) {
            $attribute_data = self::getAttributeData($product);
        }

        if (count($attribute_data) > 0) {
            $attributes = WC_Meta_Box_Product_Data::prepare_attributes($attribute_data);
            $newProduct->set_attributes($attributes);
        }
    }

    /**
     * @param Dot $product
     *
     * @return array
     *
     * @throws Exception
     */
    public static function getAttributeData($product)
    {
        $attributeData = [
            'attribute_names' => [],
            'attribute_position' => [],
            'attribute_visibility' => [],
            'attribute_values' => [],
        ];

        $added = 0;

        if ($product->has('flat_product.content_vars')) {
            foreach ($product->get('flat_product.content_vars') as $index => $cvData) {
                $contentVar = new Dot($cvData);

                if (!$contentVar->has('attribute_id')) {
                    continue;
                }

                if (!$contentVar->get('attribute_published')) {
                    continue;
                }

                ++$added;

                // Check if attribute and attribute option id exists.
                $attribute = Attributes::getAttribute($contentVar->get('attribute_id'));
                $attributeOptionsId = false;
                if ($contentVar->has('attribute_option_id') && $attribute && $attribute->slug && $attribute->name) {
                    $attributeOptionsId = Attributes::getAttributeOptionTermIdByAttributeOptionId(
                        $contentVar->get('attribute_option_id'),
                        $attribute->slug
                    );
                }

                // Check if attribute and or attribute option needs updating
                $updateAttribute = !$attribute;
                $updateAttributeOptions = !$attributeOptionsId;
                if (!$updateAttributeOptions && $contentVar->has('attribute_option_id')) {
                    $attribute_option_term = get_term($attributeOptionsId, $attribute->slug);
                    $updateAttributeOptions = $attribute_option_term->name !== $contentVar->has('value_label');
                }

                // Update both attribute and attribute options if either need updating
                if ($updateAttributeOptions || $updateAttribute) {
                    $attributeOptionsId = Attributes::updateAttributeAndOptionFromContentVar($contentVar->get());
                    $attribute = Attributes::getAttribute($contentVar->get('attribute_id'));
                }

                if ($contentVar->has('attribute_option_id')) {
                    $attributeData['attribute_names'][$index] = $attribute->slug;
                } else {
                    $attributeData['attribute_names'][$index] = $contentVar->get('label');
                }

                if ($contentVar->has('attribute_order')) {
                    $attributeOrder = $contentVar->get('attribute_order');
                } else {
                    $attributeOrder = WooCommerceAttributeMetadata::getMetadata($attribute->id, 'attribute_order', true, 'DESC') ?? 0;
                }
                $attributeData['attribute_position'][$index] = (int) $attributeOrder;

                if ($contentVar->get('attribute_published')) {
                    $attributeData['attribute_visibility'][$index] = 1;
                }

                // If the attribute options is imported
                if ($attributeOptionsId) {
                    $attributeData['attribute_values'][$index] = [
                        $attributeOptionsId,
                    ];
                } else {
                    if ($contentVar->has('value_label')) {
                        $attributeData['attribute_values'][$index] = Attributes::sanitizeOptionSlug(
                            $contentVar->get('attribute_option_id'),
                            (string) $contentVar->get('value')
                        );
                    } else {
                        $attributeData['attribute_values'][$index] = (string) $contentVar->get('value');
                    }
                }
            }
        }
        if ($added <= 0) {
            return [];
        }

        return $attributeData;
    }

    /**
     * @param Dot $dotObject
     *
     * @return bool|\WP_Post
     *
     * @throws WordpressException
     */
    protected function getAssignedProduct($dotObject)
    {
        $productCheck = self::findBackendShopProductId($dotObject->get('id'));
        if (false === $productCheck && $dotObject->has('flat_product.product.sku')) {
            $productCheck = self::getAllItemsBySku($dotObject->get('flat_product.product.sku'));
        }

        return $productCheck;
    }

    /**
     * @param Dot $dotObject
     *
     * @return bool|\WP_Post
     *
     * @throws WordpressException
     */
    public static function gettingSimpleOrConfigurableProduct($dotObject)
    {
        $productCheck = self::getItem($dotObject->get('id'));
        if (false === $productCheck && $dotObject->has('flat_product.product.sku')) {
            $productCheck = self::getItemBySku($dotObject->get('flat_product.product.sku'));
        }

        return $productCheck;
    }

    /**
     * @param $parentShopProductId
     *
     * @return Dot
     *
     * @throws Exception
     */
    protected function getParentProductObject($parentShopProductId)
    {
        $parentResponse = $this->storekeeper_api->getModule('ShopModule')->naturalSearchShopFlatProductForHooks(
            0,
            $this->getLanguage(),
            0,
            1,
            null,
            [
                [
                    'name' => 'id__=',
                    'val' => $parentShopProductId,
                ],
            ]
        );
        $parentData = $parentResponse['data'];
        if (count($parentData) <= 0) {
            throw new CannotFetchShopProductException($parentShopProductId);
        }

        return new Dot($parentData[0]);
    }

    /**
     * @param $dotObject
     *
     * @return int
     *
     * @throws WordpressException
     */
    protected function processAssignedProduct(Dot $dotObject, array $log_data)
    {
        // getting the assigned product.
        $productCheck = $this->getAssignedProduct($dotObject);
        $shopProductId = $dotObject->get('id');

        /** Check if the parent has changed. */

        // Searching for the parent.
        // TODO [profes@3/18/19]: Use cheaper function then getConfigurableShopProductOptions
        $configResponse = $this->storekeeper_api->getModule('ShopModule')->getConfigurableShopProductOptions(
            $shopProductId
        );
        $configObject = new Dot($configResponse);
        // Check if the parent shop_product_id exists.
        if (
            !array_key_exists('configurable_shop_product', $configResponse) ||
            !array_key_exists('shop_product_id', $configResponse['configurable_shop_product'])
        ) {
            throw new Exception("Could not find parent in the backend with assigned product with shop_product_id: $shopProductId");
        }

        /** Check if the parent has changed */
        $parentShopProductId = $configResponse['configurable_shop_product']['shop_product_id'];

        // Check if we have found the product with just the ID.
        $parentProductCheck = self::getItem($parentShopProductId);
        if (false === $parentProductCheck) {
            // We need to fetch the parents data to get it by SKU.
            try {
                $parentProductObject = $this->getParentProductObject($parentShopProductId);
            } catch (CannotFetchShopProductException $e) {
                $this->debug(
                    'Parent product is not visible -> import product as it was simple',
                    $log_data + [
                        'parentShopProductId' => $parentShopProductId,
                    ]
                );

                return $this->processSimpleAndConfigurableProduct(
                    $dotObject, $log_data,
                    [],
                    'simple'
                );
            }
            if ($parentProductObject->has('flat_product.product.sku')) {
                $parentProductCheck = self::getItemBySku($parentProductObject->get('flat_product.product.sku'));
            }
        }

        // Import the parent product if it does not exists.
        if (!$parentProductCheck) {
            $parentImport = new ProductImport(
                [
                    'storekeeper_id' => $parentShopProductId,
                ]
            );
            $parentImport->setLogger($this->logger);
            $parentImport->setTaskHandler($this->getTaskHandler());
            $parentImport->run();
            // Getting the imported parent product.
            $parentProductCheck = self::getItem($parentShopProductId);
        }

        // Check if the parent has changed
        $parentProduct = new WC_Product_Variable($parentProductCheck);

        // Schedule a task to calculate the current parent
        $this->getTaskHandler()->rescheduleTask(
            TaskHandler::PARENT_PRODUCT_RECALCULATION,
            "shop_product_id::$parentShopProductId",
            [
                'parent_shop_product_id' => $parentShopProductId,
            ]
        );

        // Check if the product exists and the parent product has changed
        if ($productCheck && $productCheck->post_parent !== $parentProduct->get_id()) {
            // Schedule a task to calculate the previous parent
            $this->getTaskHandler()->rescheduleTask(
                TaskHandler::PARENT_PRODUCT_RECALCULATION,
                "post_id::{$parentProduct->get_id()}",
                [
                    'parent_post_id' => $productCheck->post_parent,
                ]
            );
        }

        // Update assigned only
        $this->updateAssignedProduct($productCheck, $parentProduct, $dotObject, $configObject);

        if (!$productCheck) {
            // Getting the product id.
            $productCheck = $this->getAssignedProduct($dotObject);
        }

        return $productCheck->ID;
    }

    /**
     * @param Dot $dotObject
     *
     * @return int
     *
     * @throws WordpressException
     * @throws \WC_Data_Exception
     */
    protected function processSimpleAndConfigurableProduct(
        $dotObject,
        array $log_data,
        array $options,
        $importProductType
    ) {
        $productCheck = self::gettingSimpleOrConfigurableProduct($dotObject);

        $product_id = 0;
        if (false !== $productCheck && null !== $productCheck) {
            $product_id = $productCheck->ID;
        }

        if ('simple' === $importProductType) {
            $newProduct = new WC_Product_Simple($product_id);
        } else {
            if ('configurable' === $importProductType) {
                $newProduct = new WC_Product_Variable($product_id);
            }
        }

        // set StoreKeeperId
        $wp_type = WooCommerceOptions::getWooCommerceTypeFromProductType($importProductType);
        StoreKeeperIdCache::set($dotObject->get('id'), $wp_type);

        /* Other variables */
        $newProduct->set_sku($dotObject->get('flat_product.product.sku'));
        $log_data['sku'] = $dotObject->get('flat_product.product.sku');
        $this->debug('Set sku on product', $log_data);

        $newProduct->set_name($dotObject->get('flat_product.title'));
        $log_data['name'] = $dotObject->get('flat_product.title');
        $this->debug('Set name on product', $log_data);

        $newProduct->set_slug($dotObject->get('flat_product.slug'));
        $log_data['slug'] = $dotObject->get('flat_product.slug');
        $this->debug('Set slug on product', $log_data);

        /** Description */
        $description = $dotObject->get('flat_product.body', '');
        $description = ParseDown::wrapContentInShortCode($description);
        $newProduct->set_description($description);
        $log_data['description'] = $description;
        $this->debug('Set description on product', $log_data);

        /** Short description */
        $shortDescription = $dotObject->get('flat_product.summary', '');
        $shortDescription = ParseDown::wrapContentInShortCode($shortDescription);
        $newProduct->set_short_description($shortDescription);
        $log_data['short_description'] = $shortDescription;
        $this->debug('Set short_description on product', $log_data);

        /* Pricing */
        // Regular price
        $newProduct->set_regular_price($dotObject->get('product_default_price.ppu_wt'));
        $log_data['regular_price'] = $dotObject->get('product_default_price.ppu_wt');
        $this->debug('Set regular_price on product', $log_data);

        // WooCommece will only allow setting the sale price when it's lower then the regular price
        // When the two values are the same or the sale price is higher, it will default to no sales price
        $newProduct->set_sale_price($dotObject->get('product_price.ppu_wt'));
        $log_data['sale_price'] = $dotObject->get('product_price.ppu_wt');
        $this->debug('Set sale_Price on product', $log_data);

        /** Stock */
        $log_data = $this->setProductStock($newProduct, $dotObject, $log_data);

        /* Backorder */
        $this->setProductBackorder($newProduct, $dotObject);

        /* Categories */
        $this->setCategories($newProduct, $dotObject);
        $this->debug('Set Categories on product', $log_data);

        /* Tags */
        $this->setTags($newProduct, $dotObject);
        $this->debug('Set Tags on product', $log_data);

        /** Main image */
        $main_image_id = $this->setImage($newProduct, $dotObject);
        $this->debug('Set main Image on product', $log_data);

        /* Additional images */
        $this->setGalleryImages($newProduct, $dotObject, $main_image_id);
        $this->debug('Set GalleryImages on product', $log_data);

        if (!array_key_exists('skip_upsell', $options) || !$options['skip_upsell']) {
            /** Upsell */
            $upsell_ids = $this->setUpsellIds($newProduct, $dotObject);
            $log_data['upsell_ids'] = $upsell_ids;
            $this->debug('Set UpsellIds on product', $log_data);
        } else {
            $this->debug('Skipped UpsellIds on product', $options);
        }

        if (!array_key_exists('skip_cross_sell', $options) || !$options['skip_cross_sell']) {
            /** cross sell */
            $cross_sell_ids = $this->setCrossSellIds($newProduct, $dotObject);
            $log_data['cross_sell_ids'] = $cross_sell_ids;
            $this->debug('Set crossSellIds on product', $log_data);
        } else {
            $this->debug('Skipped crossSellIds on product', $options);
        }

        if ('simple' === $newProduct->get_type()) {
            $this->debug('Going to set attributes', $log_data);
            $this->setSimpleAttributes($newProduct, $dotObject);
            $this->debug('Set SimpleAttributes on product', $log_data);

            $post_id = $newProduct->save();
            $log_data['post_id'] = $post_id;
            $this->updatePostStatus($post_id, 'publish');
            $this->debug('Simple product saved', $log_data);
        } else {
            $ShopModule = $this->storekeeper_api->getModule('ShopModule');
            $response = $ShopModule->getConfigurableShopProductOptions(
                $dotObject->get('id'),
                ['lang' => self::getMainLanguage()]
            );
            $optionsConfig = new Dot($response);

            $this->setConfigurableAttributes($newProduct, $dotObject, $optionsConfig);

            $post_id = $newProduct->save();
            $this->updatePostStatus($post_id, 'publish');
            $log_data['post_id'] = $post_id;
            $this->debug('Configurable product saved', $log_data);

            if ($this->syncProductVariations) {
                $this->syncProductVariations($newProduct, $optionsConfig, $log_data);
            } else {
                $this->reorderProductVariations($optionsConfig);
            }
        }

        update_post_meta($newProduct->get_id(), 'storekeeper_id', $dotObject->get('id'));

        // Add last sync date meta for products
        // Time will be based on user's selected timezone on wordpress
        $date = current_time('mysql');
        update_post_meta($newProduct->get_id(), 'storekeeper_sync_date', $date);
        $this->debug('storekeeper_id added to post.', $log_data);

        if ($dotObject->has('flat_product.content_vars')) {
            $nonAttributeOptions = array_filter(
                $dotObject->get('flat_product.content_vars'),
                function ($attribute) {
                    return !key_exists('attribute_option_id', $attribute);
                }
            );
            foreach ($nonAttributeOptions as $attribute) {
                if (key_exists('attribute_id', $attribute)) {
                    $label = sanitize_title($attribute['label']);
                    $attribute_id = $attribute['attribute_id'];
                    update_post_meta($newProduct->get_id(), 'attribute_id_'.$attribute_id, $label);
                }
            }
        }

        $this->debug('Configurable product finalized', $log_data);

        return $newProduct->get_id();
    }

    /**
     * @param $taxonomy_slug
     * @param $taxonomy_name
     * @param $StoreKeeperId
     *
     * @return bool|\WP_Term
     *
     * @throws WordpressException
     */
    private function getAttributeOption($taxonomy_slug, $taxonomy_name, $StoreKeeperId)
    {
        $this->registerAttribute($taxonomy_slug, $taxonomy_name);
        $labels = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => Attributes::createWooCommerceAttributeName($taxonomy_slug),
                    'hide_empty' => false,
                    'number' => 1,
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $StoreKeeperId,
                ]
            )
        );

        if (1 === count($labels)) {
            return $labels[0];
        }

        return false;
    }

    /**
     * @param $raw_attribute_slug
     * @param $raw_attribute_name
     *
     * @throws WordpressException
     */
    private function registerAttribute($raw_attribute_slug, $raw_attribute_name)
    {    // Register as taxonomy while importing.
        $taxonomy_name = Attributes::createWooCommerceAttributeName($raw_attribute_slug);
        WordpressExceptionThrower::throwExceptionOnWpError(
            register_taxonomy(
                $taxonomy_name,
                apply_filters('woocommerce_taxonomy_objects_'.$taxonomy_name, ['product']),
                apply_filters(
                    'woocommerce_taxonomy_args_'.$taxonomy_name,
                    [
                        'labels' => [
                            'name' => $raw_attribute_name,
                        ],
                        'hierarchical' => true,
                        'show_ui' => false,
                        'query_var' => true,
                        'rewrite' => false,
                    ]
                )
            )
        );
    }

    /**
     * @param $newProduct WC_Product_Variable
     * @param Dot $product
     * @param Dot $optionsConfig
     *
     * @throws Exception
     */
    public function setConfigurableAttributes(&$newProduct, $product, $optionsConfig)
    {
        $attribute_data = [
            'attribute_names' => [],
            'attribute_position' => [],
            'attribute_visibility' => [],
            'attribute_values' => [],
        ];

        if ($product->has('flat_product.content_vars')) {
            $attribute_data = self::getAttributeData($product);
        }

        /**
         * We are going to create an attribute option id map, so we can limit the attribute options we recheck
         * $attribute_option_id_map[$attribute_id] = [...$attribute_option_id].
         */
        $attribute_option_id_map = [];
        $attribute_options = $optionsConfig->get('attribute_options');

        if (is_array($attribute_options)) {
            foreach ($attribute_options as $attribute_option) {
                if (!array_key_exists($attribute_option['attribute_id'], $attribute_option_id_map)) {
                    $attribute_option_id_map[$attribute_option['attribute_id']] = [];
                }

                $attribute_option_id_map[$attribute_option['attribute_id']][] = $attribute_option['id'];
            }
        }

        // Checking if the optionsConfig attributes are fully synced, limiting to the required attribute options ids.
        $attribute_ids = $optionsConfig->get(
            'configurable_product.configurable_product_kind.configurable_attribute_ids'
        );
        if (is_array($attribute_ids)) {
            foreach ($attribute_ids as $attribute_id) {
                if (array_key_exists($attribute_id, $attribute_option_id_map)) {
                    $A = new Attributes();
                    $A->setLogger($this->logger);
                    $A->ensureAttributeAndOptions($attribute_id, $attribute_option_id_map[$attribute_id]);
                }
            }
        }

        $configurable_attribute_array = [];

        foreach ($optionsConfig->get('attributes') as $attribute) {
            $term_name = Attributes::createWooCommerceAttributeName($attribute['name']);
            $configurable_attribute_array[$term_name] = [];
            foreach ($optionsConfig->get('attribute_options') as $attribute_option) {
                $attribute_options_id = Attributes::getAttributeOptionTermId(
                    $attribute['name'],
                    $attribute_option['name'],
                    $attribute_option['id']
                );

                $attributeOptionsOrder = $attribute_option['order'] ?? 0;
                Attributes::updateAttributeOptionOrder($attribute_options_id, $attributeOptionsOrder);

                if ($attribute_options_id) {
                    $configurable_attribute_array[$term_name][] = $attribute_options_id;
                }
            }
        }

        foreach ($configurable_attribute_array as $attribute_name => $attribute_values) {
            $index = array_search($attribute_name, $attribute_data['attribute_names'], true);

            if (false === $index) {
                $index = count($attribute_data['attribute_position']) + 1;
                $attribute_data['attribute_position'][$index] = count($attribute_data['attribute_position']);
            }

            $attribute_data['attribute_names'][$index] = $attribute_name;
            $attribute_data['attribute_visibility'][$index] = 1;

            $attribute_data['attribute_values'][$index] = $attribute_values;
            $attribute_data['attribute_variation'][$index] = 1;
        }

        $attributes = WC_Meta_Box_Product_Data::prepare_attributes($attribute_data);
        $newProduct->set_attributes($attributes);
    }

    /**
     * @param $parentProduct WC_Product_Simple|WC_Product_Variable
     * @param Dot $optionsConfig
     *
     * @throws WordpressException
     */
    private function syncProductVariations($parentProduct, $optionsConfig, $log_data)
    {
        // Sorting?
        $data_store = $parentProduct->get_data_store();
        $data_store->sort_all_product_variations($parentProduct->get_id());

        $associatedShopProducts = $optionsConfig->get('configurable_associated_shop_products', false);
        $backofficeVariationStorekeeperIds = array_column($associatedShopProducts, 'shop_product_id');

        $variationPostIds = $parentProduct->get_children();
        // Check if id no longer exist in the associated products and delete the post object
        foreach ($variationPostIds as $variationPostId) {
            $variationStorekeeperId = $this->getPostMeta($variationPostId, 'storekeeper_id', 0);
            if (!in_array($variationStorekeeperId, $backofficeVariationStorekeeperIds)) {
                wp_trash_post($variationPostId);
            }
        }

        $log_data['assigned_shop_count'] = count($associatedShopProducts);
        $this->debug('Assigned product found', $log_data);

        if (false !== $associatedShopProducts && count($associatedShopProducts) > 0) {
            foreach ($associatedShopProducts as $associatedShopProductData) {
                $assigned_debug_log = [];
                $associatedShopProduct = new Dot($associatedShopProductData);

                $variation_id = $this->getVariationId($associatedShopProduct, $assigned_debug_log);
                $variation = new WC_Product_Variation($variation_id);

                $assigned_debug_log['variation_id'] = $variation_id;
                $this->debug('variation post_id', $assigned_debug_log);

                /** Backorder */
                $trueValue = $this->getBackorderTrueValue();
                $backorder_string = $associatedShopProduct->get(
                    'shop_product.backorder_enabled',
                    false
                ) ? $trueValue : 'no';

                // TODO: use getChangedVariationProps instead of this.
                $props = [
                    'parent_id' => $parentProduct->get_id(),
                    'status' => $associatedShopProduct->get('shop_product.active') ? 'publish' : 'private',
                    'regular_price' => wc_clean(
                        $associatedShopProduct->get('shop_product.product_default_price.ppu_wt')
                    ),
                    'description' => $parentProduct->get_description('edit'),
                    'manage_stock' => !$associatedShopProduct->get('shop_product.product.product_stock.unlimited'),
                    'sku' => $associatedShopProduct->get('shop_product.product.sku'),
                    'stock_quantity' => wc_stock_amount(
                        $associatedShopProduct->get('shop_product.product.product_stock.value')
                    ),
                    'backorders' => $backorder_string,
                    'stock_status' => $associatedShopProduct->get('shop_product.product.product_stock.in_stock') ?
                        self::STOCK_STATUS_IN_STOCK : self::STOCK_STATUS_OUT_OF_STOCK,
                    'image_id' => $parentProduct->get_image_id(),
                    'shipping_class_id' => wc_clean(-1),
                    'tax_class' => 'parent',
                ];

                $sale_price = wc_clean($associatedShopProduct->get('shop_product.product_price.ppu_wt'));
                if ($props['regular_price'] != $sale_price) {
                    $props['sale_price'] = $sale_price;
                }

                $this->setAssignedAttributes(
                    $variation,
                    $optionsConfig,
                    $associatedShopProduct->get(
                        'configurable_associated_product.attribute_option_ids'
                    )
                );

                $menuOrder = Attributes::getOptionOrder($variation, $associatedShopProduct);
                $props['menu_order'] = wc_clean($menuOrder);

                WordpressExceptionThrower::throwExceptionOnWpError($variation->set_props($props));

                $post_id = $variation->save();

                $assigned_debug_log['variation_saved_id'] = $post_id;
                $this->debug('variation saved', $assigned_debug_log);

                update_post_meta(
                    $variation->get_id(),
                    'storekeeper_id',
                    $associatedShopProduct->get('shop_product_id')
                );

                $this->scheduleVariationActionTask($parentProduct->get_id());

                $this->debug('variation saved after action', $assigned_debug_log);
            }
        }
    }

    /**
     * @throws WordpressException
     */
    private function reorderProductVariations($optionsConfig): void
    {
        $associatedShopProducts = $optionsConfig->get('configurable_associated_shop_products', false);
        if (false !== $associatedShopProducts && count($associatedShopProducts) > 0) {
            foreach ($associatedShopProducts as $associatedShopProductData) {
                $assigned_debug_log = [];
                $associatedShopProduct = new Dot($associatedShopProductData);

                $variation_id = $this->getVariationId($associatedShopProduct, $assigned_debug_log);
                if (0 !== $variation_id && !is_null($variation_id)) {
                    $variation = new WC_Product_Variation($variation_id);

                    $menuOrder = Attributes::getOptionOrder($variation, $associatedShopProduct);

                    $assigned_debug_log['variation_id'] = $variation_id;
                    $this->debug('variation post_id', $assigned_debug_log);

                    $props = [
                        'menu_order' => wc_clean($menuOrder),
                    ];

                    WordpressExceptionThrower::throwExceptionOnWpError($variation->set_props($props));

                    $post_id = $variation->save();

                    $assigned_debug_log['variation_saved_id'] = $post_id;
                    $this->debug('variation reordered', $assigned_debug_log);
                }
            }
        }
    }

    /**
     * @param $StoreKeeperId
     *
     * @return bool
     *
     * @throws WordpressException
     */
    private function getProductVariation($StoreKeeperId)
    {
        $products = WordpressExceptionThrower::throwExceptionOnWpError(
            get_posts(
                [
                    'post_type' => 'product_variation',
                    'number' => 1,
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $StoreKeeperId,
                    'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
                ]
            )
        );

        if (1 === count($products)) {
            return $products[0];
        }

        return false;
    }

    /**
     * @param $sku
     *
     * @return bool
     *
     * @throws WordpressException
     */
    private function getProductVariationBySku($sku)
    {
        $products = WordpressExceptionThrower::throwExceptionOnWpError(
            get_posts(
                [
                    'post_type' => 'product_variation',
                    'number' => 1,
                    'meta_key' => '_sku',
                    'meta_value' => $sku,
                    'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
                ]
            )
        );

        if (1 === count($products)) {
            return $products[0];
        }

        return false;
    }

    /**
     * @param $newProduct WC_Product_Variation
     * @param Dot $optionsConfig
     * @param $wantedAttributeOptionIds array
     */
    private function setAssignedAttributes(&$newProduct, $optionsConfig, $wantedAttributeOptionIds)
    {
        $options = [];

        $attributes = $this->idIsKey($optionsConfig->get('attributes'));
        $attributeOptions = $this->idIsKey($optionsConfig->get('attribute_options'));

        foreach ($wantedAttributeOptionIds as $wantedId) {
            $option = $attributeOptions[$wantedId];
            $attribute = $attributes[$option['attribute_id']];
            $attrName = wc_variation_attribute_name(Attributes::createWooCommerceAttributeName($attribute['name']));
            $options[$attrName] = Attributes::sanitizeOptionSlug($option['id'], $option['name']);
        }

        $newProduct->set_attributes($options);
    }

    private function idIsKey($array, $key = 'id')
    {
        $return = [];
        foreach ($array as $item) {
            $return[$item[$key]] = $item;
        }

        return $return;
    }

    /**
     * @param $assignedProductPost \WP_Post
     * @param $parentProduct WC_Product_Variable
     * @param Dot $assignedProductData
     * @param Dot $configObject
     *
     * @return int
     *
     * @throws WordpressException
     */
    protected function updateAssignedProduct($assignedProductPost, $parentProduct, $assignedProductData, $configObject)
    {
        $variationProduct = new WC_Product_Variation($assignedProductPost);

        $props = $this->getChangedVariationProps($variationProduct, $assignedProductData, $parentProduct);
        $attribute_option_ids = $this->idIsKey($configObject->get('attribute_options'));

        // Update the attributes
        $wanted_configured_attribute_option_ids = [];
        foreach ($assignedProductData->get('flat_product.content_vars', []) as $content_var) {
            Attributes::updateAttributeAndOptionFromContentVar($content_var);
            if (array_key_exists('attribute_option_id', $content_var) &&
                array_key_exists($content_var['attribute_option_id'], $attribute_option_ids)) {
                $wanted_configured_attribute_option_ids[] = $content_var['attribute_option_id'];
            }
        }
        $this->setAssignedAttributes(
            $variationProduct,
            $configObject,
            $wanted_configured_attribute_option_ids
        ); // This may always trigger a change.

        if (count($props) > 0 || count($wanted_configured_attribute_option_ids) > 0) {
            // Setting the props.
            WordpressExceptionThrower::throwExceptionOnWpError($variationProduct->set_props($props));

            // Check if there are any prop changes
            if (count($variationProduct->get_changes()) > 0) {
                $post_id = $variationProduct->save();

                $this->scheduleVariationActionTask($parentProduct->get_id());
                $this->debug('Assigned product saved');
            }
        }

        // To make sure it still correct.
        update_post_meta($variationProduct->get_id(), 'storekeeper_id', $assignedProductData->get('id'));

        return $post_id;
    }

    /**
     * @param $variationProduct WC_Product_Variation
     * @param Dot $assignedProductData
     * @param $parentProduct WC_Product_Variable
     *
     * @return array
     */
    private function getChangedVariationProps($variationProduct, $assignedProductData, $parentProduct)
    {
        $props = [];

        // parent_id
        $parent_id = $parentProduct->get_id();
        if ($variationProduct->get_parent_id(self::EDIT_CONTEXT) != $parent_id) {
            $props['parent_id'] = $parent_id;
        }

        // status
        $status = $assignedProductData->get('active') ? 'publish' : 'private';
        if ($variationProduct->get_status(self::EDIT_CONTEXT) !== $status) {
            $props['status'] = $status;
        }

        // regular_price
        $regular_price = wc_clean($assignedProductData->get('product_default_price.ppu_wt'));
        if ($variationProduct->get_regular_price(self::EDIT_CONTEXT) != $regular_price) {
            $props['regular_price'] = $regular_price;
        }

        // sale_price
        $sale_price = wc_clean($assignedProductData->get('product_price.ppu_wt'));
        if ($variationProduct->get_sale_price(self::EDIT_CONTEXT) != $sale_price) {
            $props['sale_price'] = $sale_price;
        }

        // description
        $description = $parentProduct->get_description('edit');
        if ($variationProduct->get_description(self::EDIT_CONTEXT) !== $description) {
            $props['description'] = $description;
        }

        // manage_stock
        $in_stock = $assignedProductData->get('flat_product.product.product_stock.in_stock');
        // We always manage the stock if the product is out of stock.
        $manage_stock = !$in_stock ? true : !$assignedProductData->get('flat_product.product.product_stock.unlimited');
        if ($variationProduct->get_manage_stock(self::EDIT_CONTEXT) != $manage_stock) {
            $props['manage_stock'] = $manage_stock;
        }

        // sku
        $sku = $assignedProductData->get('flat_product.product.sku');
        if ($variationProduct->get_sku(self::EDIT_CONTEXT) !== $sku) {
            $props['sku'] = $sku;
        }

        // stock_quantity
        $this->applyStockToProps($props, $variationProduct, $assignedProductData);

        // backorders
        $backorderTrueValue = $this->getBackorderTrueValue();
        $backorders = $assignedProductData->get('backorder_enabled', false) ? $backorderTrueValue : 'no';
        if ($variationProduct->get_backorders(self::EDIT_CONTEXT) !== $backorders) {
            $props['backorders'] = $backorders;
        }

        // image_id
        $image_id = $parentProduct->get_image_id('edit');
        if ($variationProduct->get_image_id(self::EDIT_CONTEXT) != $image_id) {
            $props['image_id'] = $image_id;
        }

        // Always 0 because we don't utilize https://docs.woocommerce.com/document/product-shipping-classes/
        $shipping_class_id = 0;
        if ($variationProduct->get_shipping_class_id(self::EDIT_CONTEXT) != $shipping_class_id) {
            $props['shipping_class_id'] = $shipping_class_id;
        }

        $tax_class = 'parent';
        if ($variationProduct->get_tax_class(self::EDIT_CONTEXT) !== $tax_class) {
            $props['tax_class'] = $tax_class;
        }

        return $props;
    }

    protected function afterRun()
    {
        $data = [];
        if (
            $this->storekeeper_id > 0
            && StoreKeeperOptions::exists(StoreKeeperOptions::MAIN_CATEGORY_ID) &&
            StoreKeeperOptions::get(StoreKeeperOptions::MAIN_CATEGORY_ID) > 0
        ) {
            $wanted_cate_id = StoreKeeperOptions::get(StoreKeeperOptions::MAIN_CATEGORY_ID);
            $data['wanted_cate_id'] = $wanted_cate_id;
            $this->debug('Fetching product to check', $data);
            $response = $this->storekeeper_api->getModule('ShopModule')->naturalSearchShopFlatProductForHooks(
                0,
                0,
                0,
                1,
                null,
                [
                    [
                        'name' => 'id__=',
                        'val' => $this->storekeeper_id,
                    ],
                ]
            );
            $data = $response['data'];
            if (1 === count($data)) {
                $this->debug('Found one product', $data);
                $product_data = current($data);
                $product = new Dot($product_data);
                $category_ids = [];
                if ($product->has('flat_product.categories')) {
                    $categories = $product->get('flat_product.categories');
                    foreach ($categories as $category) {
                        $category_ids[] = $category['id'];
                    }
                }
                $data['product_cate_id'] = $category_ids;
                $this->debug('Found categoryies', $category_ids);

                $post = self::getItem($product->get('id'));
                if (false === $post && $product->has('flat_product.product.sku')) {
                    $post = (false === $post) ? self::getItemBySku($product->get('flat_product.product.sku')) : $post;
                }

                $this->debug('Searched for post', $post);

                if (false === array_search($wanted_cate_id, $category_ids)) {
                    $this->debug('Disabling product', $data);

                    if (false !== $post) {
                        wp_update_post(
                            [
                                'ID' => $post->ID,
                                'post_status' => 'private',
                            ]
                        );
                        $this->debug('Product disabled');
                    } else {
                        $this->debug('Failed to disable product');
                    }
                } else {
                    $this->debug('enabling product', $data);

                    if (false !== $post) {
                        wp_update_post(
                            [
                                'ID' => $post->ID,
                                'post_status' => 'publish',
                            ]
                        );
                        $this->debug('Product enabled');
                    } else {
                        $this->debug('Failed to enable product');
                    }
                }
            }
        }
    }

    /**
     * @param $parentProductId
     *
     * @throws WordpressException
     */
    private function scheduleVariationActionTask($parentProductId)
    {
        // Schedules a tasks to trigger the save action of all variations.
        TaskHandler::scheduleTask(
            TaskHandler::TRIGGER_VARIATION_SAVE_ACTION,
            $parentProductId,
            [
                'parent_id' => $parentProductId,
            ]
        );
    }

    private function applyStockToProps(
        array &$props,
        WC_Product_Variation $variationProduct,
        Dot $assignedProductData
    ) {
        $manage_stock = true;
        $stock_quantity = 0;
        $stock_status = self::STOCK_STATUS_OUT_OF_STOCK;
        if ($assignedProductData->get('flat_product.product.product_stock.in_stock')) {
            $manage_stock = !$assignedProductData->get('flat_product.product.product_stock.unlimited');
            $stock_quantity = $manage_stock ? $assignedProductData->get(
                'flat_product.product.product_stock.value'
            ) : 9999;
            $stock_status = self::STOCK_STATUS_IN_STOCK;
        }

        if ($variationProduct->get_manage_stock() !== $manage_stock) {
            $props['manage_stock'] = $manage_stock;
        }
        if ($variationProduct->get_stock_quantity() !== $stock_quantity) {
            $props['stock_quantity'] = $stock_quantity;
        }
        if ($variationProduct->get_stock_status() !== $stock_status) {
            $props['stock_status'] = $stock_status;
        }
    }

    protected function getPostMeta($postId, string $metaKey, $fallback)
    {
        if (metadata_exists('post', $postId, $metaKey)) {
            return get_post_meta($postId, $metaKey, true);
        }

        return $fallback;
    }

    public function setSyncProductVariations(bool $isProductVariable): void
    {
        $this->syncProductVariations = $isProductVariable;
    }

    public function getSyncProductVariations()
    {
        return $this->syncProductVariations;
    }

    /**
     * @throws WordpressException
     */
    private function getVariationId(Dot $associatedShopProduct, array &$assigned_debug_log): int
    {
        $variationCheck = $this->getProductVariation($associatedShopProduct->get('shop_product_id'));
        if (false === $variationCheck && $associatedShopProduct->has('shop_product.product.sku')) {
            $variationCheck = (false === $variationCheck) ? $this->getProductVariationBySku(
                $associatedShopProduct->get('shop_product.product.sku')
            ) : $variationCheck;
        }
        $assigned_debug_log['assigned_shop_id'] = $associatedShopProduct->get('shop_product_id');
        $this->debug('Assigned product added', $assigned_debug_log);

        $variationId = 0;
        if (false !== $variationCheck) {
            $variationId = $variationCheck->ID;
        }

        return $variationId;
    }
}
