<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use Exception;
use StoreKeeper\WooCommerce\B2C\Cache\ShopProductCache;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Exceptions\CannotFetchShopProductException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\RankMathSeo;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\StoreKeeperSeo;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\YoastSeo;
use StoreKeeper\WooCommerce\B2C\Helpers\WpCliHelper;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\WithConsoleProgressBarInterface;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;
use StoreKeeper\WooCommerce\B2C\Tools\Categories;
use StoreKeeper\WooCommerce\B2C\Tools\Media;
use StoreKeeper\WooCommerce\B2C\Tools\ParseDown;
use StoreKeeper\WooCommerce\B2C\Tools\ProductAttributes;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use StoreKeeper\WooCommerce\B2C\Traits\ConsoleProgressBarTrait;
use WC_Product;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;

class ProductImport extends AbstractProductImport implements WithConsoleProgressBarInterface
{
    use ConsoleProgressBarTrait;

    public const PRODUCT_EMBALLAGE_PRICE_META_KEY = 'storekeeper_emballage_price';
    public const PRODUCT_EMBALLAGE_PRICE_WT_META_KEY = 'storekeeper_emballage_price_wt';
    public const PRODUCT_EMBALLAGE_TAX_ID_META_KEY = 'storekeeper_emballage_tax_id';

    public const CATEGORY_TAG_MODULE = 'ProductsModule';
    public const CATEGORY_ALIAS = 'Product';
    public const TAG_ALIAS = 'Label';
    public const META_HAS_ADDONS = 'storekeeper_has_addons';

    protected $syncProductVariations = false;
    protected $newItemsCount = 0;
    protected $updatedItemsCount = 0;

    protected bool $skipBroken = false;

    public function isSkipBroken(): bool
    {
        return $this->skipBroken;
    }

    public function setSkipBroken(bool $skipBroken): void
    {
        $this->skipBroken = $skipBroken;
    }

    /**
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

    public function __construct(array $settings = [])
    {
        if (array_key_exists('hide-progress-bar', $settings)) {
            $this->setIsProgressBarShown(false);
        }
        parent::__construct($settings);
    }

    /**
     * @param Dot $dotObject
     *
     * @return bool|int
     *
     * @throws WordpressException
     * @throws \WC_Data_Exception
     */
    protected function doProcessProductItem($dotObject, array $options = [])
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

    protected function updatePostStatus($post_id, $status)
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
     * @param     $newProduct WC_Product_Simple|WC_Product_Variable
     * @param Dot $product
     *
     * @throws WordpressException
     */
    protected function setCategories(&$newProduct, $product)
    {
        $categoryIds = [];

        if ($product->has('flat_product.categories')) {
            foreach ($product->get('flat_product.categories') as $category) {
                $productCategory = Categories::getCategoryById($category['id'], $category['slug']);
                if (false !== $productCategory) {
                    $categoryIds[] = $productCategory->term_id;
                } elseif (isset($category['category_type'])) {
                    $categoryType = $category['category_type'];
                    if ((self::CATEGORY_TAG_MODULE === $categoryType['module_name']) && self::CATEGORY_ALIAS === $categoryType['alias']) {
                        $productCategory = Categories::getCategoryBySlug($category['slug']);
                        if (false !== $productCategory) {
                            $categoryIds[] = $productCategory->term_id;
                        }
                    }
                }
            }
        }

        $newProduct->set_category_ids($categoryIds);
    }

    /**
     * @param     $newProduct WC_Product_Simple|WC_Product_Variable
     * @param Dot $product
     *
     * @throws WordpressException
     */
    protected function setTags(&$newProduct, $product)
    {
        $tagIds = [];

        if ($product->has('flat_product.categories')) {
            foreach ($product->get('flat_product.categories') as $category) {
                $tag = $this->getLabelById($category['id']);
                if (false !== $tag) {
                    $tagIds[] = $tag->term_id;
                } elseif (isset($category['category_type'])) {
                    $categoryType = $category['category_type'];
                    if (self::CATEGORY_TAG_MODULE === $categoryType['module_name'] && self::TAG_ALIAS === $categoryType['alias']) {
                        $tag = $this->getLabelBySlug($category['slug']);
                        if (false !== $tag) {
                            $tagIds[] = $tag->term_id;
                        }
                    }
                }
            }
        }

        $newProduct->set_tag_ids($tagIds);
    }

    private function getLabelBySlug($slug)
    {
        $labels = WordpressExceptionThrower::throwExceptionOnWpError(
            get_term_by('slug', $slug, 'product_tag')
        );

        if ($labels) {
            return $labels;
        }

        return false;
    }

    /**
     * @return bool \WP_Term
     *
     * @throws WordpressException
     */
    private function getLabelById($storekeeperId)
    {
        $labels = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => 'product_tag',
                    'hide_empty' => false,
                    'number' => 1,
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $storekeeperId,
                ]
            )
        );

        if (1 === count($labels)) {
            return $labels[0];
        }

        return false;
    }

    protected function setImage(\WC_Product $newProduct, $product)
    {
        if ($product->has('flat_product.main_image')) {
            $oldAttachmentId = (int) $newProduct->get_image_id();

            if ($product->has('flat_product.main_image.cdn_url') && StoreKeeperOptions::isImageCdnEnabled()) {
                $attachmentId = Media::createAttachmentUsingCDN($product->get('flat_product.main_image.cdn_url'));
            } else {
                $attachmentId = Media::createAttachment($product->get('flat_product.main_image.big_url'));
            }

            // Permanently remove attachment if no longer used, may it be CDN or downloaded
            if ($attachmentId && $oldAttachmentId && $oldAttachmentId !== $attachmentId) {
                $this->removeAttachment($oldAttachmentId);
            }

            $this->debug(
                'Found main product image',
                [
                    'product_images' => $product->get('flat_product.main_image'),
                    'attachment_id' => $attachmentId,
                ]
            );
            $newProduct->set_image_id($attachmentId);

            return $product->get('flat_product.main_image.id');
        }

        return null;
    }

    protected function setGalleryImages(&$newProduct, $product, $mainImageId = null): void
    {
        $attachmentIds = [];
        $oldAttachmentIds = $newProduct->get_gallery_image_ids();
        $count = 0;
        if ($product->has('flat_product.product_images')) {
            $count = count($product->get('flat_product.product_images'));
            foreach ($product->get('flat_product.product_images') as $productImage) {
                if ($productImage['id'] !== $mainImageId) {
                    if (isset($productImage['cdn_url']) && StoreKeeperOptions::isImageCdnEnabled()) {
                        $attachmentIds[] = Media::createAttachmentUsingCDN($productImage['cdn_url']);
                    } else {
                        $attachmentIds[] = Media::createAttachment($productImage['big_url']);
                    }
                }
            }
        }

        $abandonedAttachmentIds = array_diff($oldAttachmentIds, $attachmentIds);

        foreach ($abandonedAttachmentIds as $abandonedAttachmentId) {
            $this->removeAttachment($abandonedAttachmentId);
        }

        $this->debug(
            "Found $count product images",
            [
                'product_images' => $product->get('flat_product.product_images'),
                'attachment_ids' => $attachmentIds,
            ]
        );

        $newProduct->set_gallery_image_ids($attachmentIds);
    }

    protected function removeAttachment($attachmentId): void
    {
        $this->debug(
            'Permanently removing attachment',
            [
                'attachment_id' => $attachmentId,
            ]
        );

        WordpressExceptionThrower::throwExceptionOnWpError(
            wp_delete_attachment($attachmentId, true)
        );
    }

    /**
     * @param \WC_Product_Simple|\WC_Product_Variable $newProduct
     * @param Dot                                     $product
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
     * @param \WC_Product_Simple|\WC_Product_Variable $newProduct
     * @param Dot                                     $product
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
     * @return Dot
     *
     * @throws \Exception
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
            !array_key_exists('configurable_shop_product', $configResponse)
            || !array_key_exists('shop_product_id', $configResponse['configurable_shop_product'])
        ) {
            throw new \Exception("Could not find parent in the backend with assigned product with shop_product_id: $shopProductId");
        }

        $this->debug('Loaded AssignedProduct configuration', [
            'configurable_shop_product' => $configResponse['configurable_shop_product'],
            'storekeeper_shop_product_id' => $shopProductId,
        ]);

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
            $parentImport = new ProductParentImport(
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
        $parentProduct = new \WC_Product_Variable($parentProductCheck);

        $this->debug('Recalculating parents', [
            'parent_shop_product_id' => $parentShopProductId,
            'storekeeper_shop_product_id' => $shopProductId,
            'parent_post_id' => $productCheck instanceof \WP_Post ? $productCheck->post_parent : null,
        ]);

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
            ++$this->newItemsCount;
        } else {
            ++$this->updatedItemsCount;
        }

        return $productCheck->ID;
    }

    /**
     * @throws \WC_Data_Exception
     * @throws WordpressException
     */
    protected function processSimpleAndConfigurableProduct(
        Dot $dotObject,
        array $log_data,
        array $options,
        string $importProductType
    ): int {
        $newProduct = $this->ensureWooCommerceProduct($dotObject, $importProductType);

        $this->processSeo($newProduct, $dotObject);
        $log_data = $this->setProductDetails($newProduct, $dotObject, $importProductType, $log_data);
        $log_data = $this->setProductVisibility($dotObject, $newProduct, $log_data);
        $log_data = $this->setProductPrice($newProduct, $dotObject, $log_data);
        $log_data = $this->setProductStock($newProduct, $dotObject, $log_data);
        $log_data = $this->handleUpsellProducts($newProduct, $dotObject, $options, $log_data);
        $log_data = $this->handleCrossSellProducts($newProduct, $dotObject, $options, $log_data);
        $log_data = $this->saveProduct($newProduct, $dotObject, $log_data);
        $this->updateProductMeta($newProduct, $dotObject, $log_data);

        return $newProduct->get_id();
    }

    /**
     * @return \WC_Product_Simple|\WC_Product_Variable
     *
     * @throws WordpressException
     */
    protected function ensureWooCommerceProduct(Dot $dotObject, string $importProductType)
    {
        $newProduct = null;
        $productCheck = self::gettingSimpleOrConfigurableProduct($dotObject);

        $product_id = 0;
        if (false !== $productCheck && null !== $productCheck) {
            $product_id = $productCheck->ID;
            ++$this->updatedItemsCount;
        } else {
            ++$this->newItemsCount;
        }

        if ('simple' === $importProductType) {
            $newProduct = new \WC_Product_Simple($product_id);
        } elseif ('configurable' === $importProductType) {
            $newProduct = new \WC_Product_Variable($product_id);
        }

        if (is_null($newProduct)) {
            throw new \Exception("No product is association with id={$product_id}");
        }

        $this->setWoocommerceProductId($newProduct->get_id());

        return $newProduct;
    }

    /**
     * @param \WC_Product_Simple|\WC_Product_Variable $newProduct
     */
    protected function setProductPrice($newProduct, Dot $dotObject, array $log_data): array
    {
        $regularPricePerUnit = $dotObject->get('product_default_price.ppu_wt');
        /* Pricing */
        // Regular price
        $newProduct->set_regular_price($regularPricePerUnit);
        $log_data['regular_price'] = $regularPricePerUnit;
        $this->debug('Set regular_price on product', $log_data);

        // WooCommece will only allow setting the sale price when it's lower then the regular price
        // When the two values are the same or the sale price is higher, it will default to no sales price
        $discountedPricePerUnit = $dotObject->get('product_price.ppu_wt');
        $newProduct->set_sale_price($discountedPricePerUnit);
        $log_data['sale_price'] = $discountedPricePerUnit;
        $this->debug('Set sale_Price on product', $log_data);

        // Add emballage price to meta data
        if ($dotObject->has('product_emballage_price_id') && $dotObject->has('product_emballage_price')) {
            $newProduct->update_meta_data(self::PRODUCT_EMBALLAGE_PRICE_META_KEY, $dotObject->get('product_emballage_price.ppu'));
            $newProduct->update_meta_data(self::PRODUCT_EMBALLAGE_PRICE_WT_META_KEY, $dotObject->get('product_emballage_price.ppu_wt'));
            $newProduct->update_meta_data(self::PRODUCT_EMBALLAGE_TAX_ID_META_KEY, $dotObject->get('product_emballage_price.tax_rate_id'));
        }

        return $log_data;
    }

    /**
     * @param \WC_Product_Simple|\WC_Product_Variable $newProduct
     *
     * @throws WordpressException|\WC_Data_Exception
     */
    protected function setProductDetails($newProduct, Dot $dotObject, string $importProductType, array $log_data): array
    {
        // set StoreKeeperId
        $wp_type = WooCommerceOptions::getWooCommerceTypeFromProductType($importProductType);
        ShopProductCache::set($dotObject->get('id'), $wp_type);

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

        /* Categories */
        $this->setCategories($newProduct, $dotObject);
        $this->debug('Set Categories on product', $log_data);

        /* Tags */
        $this->setTags($newProduct, $dotObject);
        $this->debug('Set Tags on product', $log_data);

        /* Main image */
        $main_image_id = $this->setImage($newProduct, $dotObject);
        $this->debug('Set main Image on product', $log_data);

        /* Additional images */
        $this->setGalleryImages($newProduct, $dotObject, $main_image_id);
        $this->debug('Set GalleryImages on product', $log_data);

        return $log_data;
    }

    /**
     * @param \WC_Product_Simple|\WC_Product_Variable $newProduct
     *
     * @throws WordpressException
     * @throws \WC_Data_Exception
     */
    protected function handleUpsellProducts($newProduct, Dot $dotObject, array $options, array $log_data): array
    {
        if (!array_key_exists('skip_upsell', $options) || !$options['skip_upsell']) {
            /** Upsell */
            $upsell_ids = $this->setUpsellIds($newProduct, $dotObject);
            $log_data['upsell_ids'] = $upsell_ids;
            $this->debug('Set UpsellIds on product', $log_data);
        } else {
            $this->debug('Skipped UpsellIds on product', $options);
        }

        return $log_data;
    }

    /**
     * @param \WC_Product_Simple|\WC_Product_Variable $newProduct
     */
    protected function handleCrossSellProducts($newProduct, Dot $dotObject, array $options, array $log_data): array
    {
        if (!array_key_exists('skip_cross_sell', $options) || !$options['skip_cross_sell']) {
            /** Cross-sell */
            $cross_sell_ids = $this->setCrossSellIds($newProduct, $dotObject);
            $log_data['cross_sell_ids'] = $cross_sell_ids;
            $this->debug('Set crossSellIds on product', $log_data);
        } else {
            $this->debug('Skipped crossSellIds on product', $options);
        }

        return $log_data;
    }

    /**
     * @param \WC_Product_Simple|\WC_Product_Variable $newProduct
     */
    protected function updateProductMeta($newProduct, Dot $dotObject, array $log_data): void
    {
        update_post_meta($newProduct->get_id(), 'storekeeper_id', $dotObject->get('id'));

        $date = DatabaseConnection::formatToDatabaseDate();
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

        if ($dotObject->has('flat_product.product.has_addons')) {
            update_post_meta(
                $newProduct->get_id(),
                self::META_HAS_ADDONS,
                $dotObject->get('flat_product.product.has_addons') ? '1' : '0',
            );
        }

        $this->debug('Configurable product finalized', $log_data);
    }

    /**
     * @param \WC_Product_Simple|\WC_Product_Variable $newProduct
     *
     * @throws WordpressException
     */
    protected function saveProduct($newProduct, Dot $dotObject, array $log_data): array
    {
        $productAttributes = new ProductAttributes($this->logger);
        $pricePerUnit = $dotObject->get('product_default_price.ppu_wt');
        if ('simple' === $newProduct->get_type()) {
            $this->debug('Going to set attributes', $log_data);
            $productAttributes->setSimpleAttributes($newProduct, $dotObject);
            $this->debug('Set SimpleAttributes on product', $log_data);

            $post_id = $newProduct->save();
            $log_data['post_id'] = $post_id;
            $productStatus = $this->getProductStatusByPrice((float) $pricePerUnit);
            $this->updatePostStatus($post_id, $productStatus);
            $this->debug('Simple product saved', $log_data);
        } else {
            $ShopModule = $this->storekeeper_api->getModule('ShopModule');
            $response = $ShopModule->getConfigurableShopProductOptions(
                $dotObject->get('id'),
                ['lang' => self::getMainLanguage()]
            );
            $optionsConfig = new Dot($response);

            $productAttributes->setConfigurableAttributes($newProduct, $dotObject, $optionsConfig);

            $post_id = $newProduct->save();
            $productStatus = $this->getProductStatusByPrice((float) $pricePerUnit);
            $this->updatePostStatus($post_id, $productStatus);
            $log_data['post_id'] = $post_id;
            $this->debug('Configurable product saved', $log_data);

            if ($this->syncProductVariations) {
                $this->syncProductVariations($newProduct, $optionsConfig, $log_data);
            } else {
                $this->reorderProductVariations($optionsConfig);
            }
        }

        return $log_data;
    }

    protected function getProductStatusByPrice(float $price): string
    {
        return $price <= 0 ? 'private' : 'publish';
    }

    /**
     * @param \WC_Product_Simple|\WC_Product_Variable $product
     *
     * @throws WordpressException
     */
    protected function processSeo($product, Dot $dotObject): void
    {
        $seoTitle = null;
        $seoDescription = null;
        $seoKeywords = null;

        if ($dotObject->has('flat_product.seo_title')) {
            $seoTitle = $dotObject->get('flat_product.seo_title');
        }

        if ($dotObject->has('flat_product.seo_description')) {
            $seoDescription = $dotObject->get('flat_product.seo_description');
        }

        if ($dotObject->has('flat_product.seo_keywords')) {
            $seoKeywords = $dotObject->get('flat_product.seo_keywords');
        }

        if (YoastSeo::shouldAddSeo($seoTitle, $seoDescription, $seoKeywords)) {
            YoastSeo::addSeoToWoocommerceProduct($product, $seoTitle, $seoDescription, $seoKeywords);
        }

        if (RankMathSeo::shouldAddSeo($seoTitle, $seoDescription, $seoKeywords)) {
            RankMathSeo::addSeoToWoocommerceProduct($product, $seoTitle, $seoDescription, $seoKeywords);
        }

        StoreKeeperSeo::setProductSeo($product, $seoTitle, $seoDescription, $seoKeywords);
    }

    /**
     * @param     $parentProduct WC_Product_Simple|WC_Product_Variable
     * @param Dot $optionsConfig
     *
     * @throws WordpressException
     */
    protected function syncProductVariations($parentProduct, $optionsConfig, $log_data)
    {
        // Sorting?
        $data_store = $parentProduct->get_data_store();
        $data_store->sort_all_product_variations($parentProduct->get_id());

        $associatedShopProducts = $optionsConfig->get('configurable_associated_shop_products', false);
        $backofficeVariationStorekeeperIds = array_column($associatedShopProducts, 'shop_product_id');
        sort($backofficeVariationStorekeeperIds);

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
            $ShopModule = $this->storekeeper_api->getModule('ShopModule');
            $response = $ShopModule->naturalSearchShopFlatProductForHooks(
                0,
                self::getMainLanguage(),
                0,
                0,
                null,
                [
                    [
                        'name' => 'id__in_list',
                        'multi_val' => $backofficeVariationStorekeeperIds,
                    ],
                ]
            );

            foreach ($response['data'] as $associatedShopProductData) {
                $assigned_debug_log = [];
                $associatedShopProduct = new Dot($associatedShopProductData);

                $productCheck = $this->getAssignedProduct($associatedShopProduct);
                $variation_id = $this->updateAssignedProduct(
                    $productCheck,
                    $parentProduct,
                    $associatedShopProduct,
                    $optionsConfig
                );
                $assigned_debug_log['variation_id'] = $variation_id;
                $this->debug('variation post_id', $assigned_debug_log);

                $this->scheduleVariationActionTask($parentProduct->get_id());
                $this->debug('variation saved after action', $assigned_debug_log);
            }
        }
    }

    /**
     * @throws WordpressException
     */
    protected function reorderProductVariations($optionsConfig): void
    {
        $associatedShopProducts = $optionsConfig->get('configurable_associated_shop_products');
        if (count($associatedShopProducts) > 0) {
            $sk_attribute = current(ProductAttributes::getSortedAttributes($optionsConfig->get('attributes')));
            $firstAttribute = Attributes::getAttribute($sk_attribute['id']);
            if (is_null($firstAttribute)) {
                throw new \Exception("Attribute id={$sk_attribute['id']} is not synchronized yet");
            }

            foreach ($associatedShopProducts as $associatedShopProductData) {
                $assigned_debug_log = [];
                $associatedShopProduct = new Dot($associatedShopProductData);
                $variation_id = $this->getVariationId($associatedShopProduct, $assigned_debug_log);

                if ($variation_id) {
                    $variation = new \WC_Product_Variation($variation_id);
                    $variation_attributes = $variation->get_attributes();
                    $option_slug = $variation_attributes[$firstAttribute->slug];
                    $term_ids = get_terms([
                        'taxonomy' => $firstAttribute->slug,
                        'hide_empty' => false,
                        'fields' => 'ids',
                        'slug' => $option_slug,
                    ]);
                    if (0 === count($term_ids)) {
                        throw new \Exception("Attribute option id={$sk_attribute['id']} (attr=$firstAttribute->slug,term=$option_slug) is not synchronized");
                    }
                    $term_id = $term_ids[0];
                    $menuOrder = get_term_meta($term_id, 'order', true);
                    if (empty($menuOrder)) {
                        $menuOrder = 0;
                    }
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

        return null;
    }

    /**
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

        return null;
    }

    private function getArrayById($array, $key = 'id')
    {
        $return = [];
        foreach ($array as $item) {
            $return[$item[$key]] = $item;
        }

        return $return;
    }

    protected static function getAssignedWantedAttributes(Dot $assignedProductData, array $attribute_options_by_id): array
    {
        $att_to_option = [];
        foreach ($assignedProductData->get('flat_product.content_vars', []) as $content_var) {
            if (
                array_key_exists('attribute_option_id', $content_var)
                && array_key_exists($content_var['attribute_option_id'], $attribute_options_by_id)
            ) {
                $att_to_option[$content_var['attribute_id']] = $content_var['attribute_option_id'];
            }
        }

        return $att_to_option;
    }

    /**
     * @param     $parentProduct       WC_Product_Variable
     * @param Dot $assignedProductData
     * @param Dot $configObject
     *
     * @return int
     *
     * @throws WordpressException
     */
    protected function updateAssignedProduct($assignedProductPost, \WC_Product_Variable $parentProduct, $assignedProductData, $configObject)
    {
        $this->debug('Update assigned product', [
            'post_id' => $assignedProductPost instanceof \WP_Post ? $assignedProductPost->ID : $assignedProductPost,
            'parent_post_id' => $parentProduct->get_id(),
        ]);

        $variationProduct = new \WC_Product_Variation($assignedProductPost);
        $attribute_options_by_id = $this->getArrayById($configObject->get('attribute_options'));

        $configurable_options = self::getAssignedWantedAttributes($assignedProductData, $attribute_options_by_id);

        $firstAttributeId = $configObject->get('attributes.0.id');
        $menuOrder = 0;
        foreach ($assignedProductData->get('flat_product.content_vars', []) as $content_var) {
            if (isset($content_var['attribute_id']) && $content_var['attribute_id'] == $firstAttributeId) {
                $menuOrder = $content_var['attribute_option_order'] ?? 0;
            }
        }

        $props = $this->getChangedVariationProps($variationProduct, $assignedProductData, $parentProduct);
        if ($variationProduct->get_menu_order(self::EDIT_CONTEXT) !== $menuOrder) {
            $props['menu_order'] = wc_clean($menuOrder);
        }

        $productAttributes = new ProductAttributes($this->logger);
        $productAttributes->setAssignedAttributes(
            $variationProduct,
            $configObject,
            $configurable_options
        ); // This may always trigger a change.

        // Setting the props.
        WordpressExceptionThrower::throwExceptionOnWpError($variationProduct->set_props($props));

        $this->debug('Saved variation attributes', [
            'post_id' => $assignedProductPost instanceof \WP_Post ? $assignedProductPost->ID : $assignedProductPost,
            'parent_post_id' => $parentProduct->get_id(),
            'props' => $props,
        ]);

        $barcode_was_set = ProductAttributes::setBarcodeMeta($variationProduct, $assignedProductData);

        $this->debug('Saved barcode', [
            'post_id' => $assignedProductPost instanceof \WP_Post ? $assignedProductPost->ID : $assignedProductPost,
            'barcode_was_set' => $barcode_was_set,
        ]);

        $post_id = $variationProduct->save();

        $this->scheduleVariationActionTask($parentProduct->get_id());
        $this->debug('Assigned product saved', [
            'parent_id' => $parentProduct->get_id(),
            'id' => $post_id,
            'storekeeper_shop_product_id' => $assignedProductData->get('id'),
        ]);

        // To make sure it still correct.
        update_post_meta($variationProduct->get_id(), 'storekeeper_id', $assignedProductData->get('id'));

        return $post_id;
    }

    /**
     * @param     $variationProduct    WC_Product_Variation
     * @param Dot $assignedProductData
     * @param     $parentProduct       WC_Product_Variable
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

    protected function afterRun(array $storeKeeperIds)
    {
        $data = [];
        if (
            $this->storekeeper_id > 0
            && StoreKeeperOptions::exists(StoreKeeperOptions::MAIN_CATEGORY_ID)
            && StoreKeeperOptions::get(StoreKeeperOptions::MAIN_CATEGORY_ID) > 0
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

        WpCliHelper::attemptSuccessOutput(sprintf(
            __('Done processing %s items of %s (%s new / %s updated)', I18N::DOMAIN),
            $this->getProcessedItemCount(),
            $this->getImportEntityName(),
            $this->getNewItemsCount(),
            $this->getUpdatedItemsCount()
        ));
    }

    /**
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
        \WC_Product_Variation $variationProduct,
        Dot $assignedProductData
    ) {
        [$manage_stock, $stock_quantity, $stock_status] = $this->getStockProperties(
            $assignedProductData,
        );
        if ($variationProduct->get_manage_stock(self::EDIT_CONTEXT) != $manage_stock) {
            $props['manage_stock'] = $manage_stock;
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
    private function getVariationId(Dot $associatedShopProduct, array &$assigned_debug_log): ?int
    {
        $variation = $this->getProductVariation($associatedShopProduct->get('shop_product_id'));
        if (is_null($variation) && $associatedShopProduct->has('shop_product.product.sku')) {
            $variation = $this->getProductVariationBySku(
                $associatedShopProduct->get('shop_product.product.sku')
            );
        }
        $assigned_debug_log['assigned_shop_id'] = $associatedShopProduct->get('shop_product_id');
        $this->debug('Assigned product added', $assigned_debug_log);

        if ($variation) {
            return $variation->ID;
        }

        return null;
    }

    public function getUpdatedItemsCount(): int
    {
        return $this->updatedItemsCount;
    }

    public function getNewItemsCount(): int
    {
        return $this->newItemsCount;
    }

    protected function getImportEntityName(): string
    {
        return __('products', I18N::DOMAIN);
    }

    protected function setProductVisibility(Dot $dotObject, $newProduct, array $log_data): array
    {
        if ($dotObject->has('web_visible_in_search') || $dotObject->has('web_visible_in_catalog')) {
            $web_visible_in_search = $dotObject->get('web_visible_in_search');
            $web_visible_in_catalog = $dotObject->get('web_visible_in_catalog');
            $mode = 'hidden';
            if ($web_visible_in_search && $web_visible_in_catalog) {
                $mode = 'visible';
            } elseif ($web_visible_in_search) {
                $mode = 'search';
            } elseif ($web_visible_in_catalog) {
                $mode = 'catalog';
            }
            $newProduct->set_catalog_visibility($mode);
            $log_data['visibility'] = $mode;
        }

        return $log_data;
    }
}
