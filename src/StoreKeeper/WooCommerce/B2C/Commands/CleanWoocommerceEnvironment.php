<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;
use StoreKeeper\WooCommerce\B2C\Tools\StringFunctions;

class CleanWoocommerceEnvironment extends AbstractCommand
{
    protected $silent = false;
    protected $productsOnly = false;

    protected $productIds;
    protected $productVariationIds;
    protected $productAttachmentIds;

    /**
     * WP_CLI commands wrapper for the silent property.
     */
    protected function line()
    {
        if (!$this->silent) {
            \WP_CLI::line();
        }
    }

    protected function warning($message)
    {
        if (!$this->silent) {
            \WP_CLI::warning($message);
        }
    }

    protected function log($message)
    {
        if (!$this->silent) {
            \WP_CLI::log($message);
        }
    }

    protected function success($message)
    {
        if (!$this->silent) {
            \WP_CLI::success($message);
        }
    }

    protected function error($message, $exit = true)
    {
        if (!$this->silent) {
            \WP_CLI::error($message, $exit);
        }
    }

    public static function getShortDescription(): string
    {
        return __('Purge all Storekeeper WooCommerce related entities/objects.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Execute this command to delete all tags, coupons, attribute values, attributes, categories, products, orders, tasks and web hook logs.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'flag',
                'name' => 'yes',
                'description' => __('Skips the confirmation whether or not you are sure to continue.', I18N::DOMAIN),
                'optional' => true,
            ],
            [
                'type' => 'flag',
                'name' => 'silent',
                'description' => __('Suppress the logs of this command.', I18N::DOMAIN),
                'optional' => true,
            ],
            [
                'type' => 'flag',
                'name' => 'products-only',
                'description' => __('Clean products only.', I18N::DOMAIN),
                'optional' => true,
            ],
        ];
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        global $wpdb;

        $this->silent = array_key_exists('silent', $assoc_arguments);
        $productsOnly = array_key_exists('products-only', $assoc_arguments);

        $this->productIds = self::getProductIds();
        $this->productVariationIds = self::getProductVariationIds();
        $this->productAttachmentIds = self::getProductAttachmentIds();

        $this->line();
        $this->warning('Counts of entities to be deleted:');
        $this->line();

        if ($productsOnly) {
            $this->log('Products: '.count($this->productIds));
            $this->log('Products variations: '.count($this->productVariationIds));
            $this->log('Product images: '.count($this->productAttachmentIds));

            if (!array_key_exists('yes', $assoc_arguments)) {
                $this->line();
                $this->warning(
                    'You are about to delete all products in this environment.'
                );
                $this->warning('Make sure you have a working backup available.');
                $this->line();

                \WP_CLI::confirm('Are you sure you want to permanently delete entities?');
            }
        } else {
            $tag_terms = self::getTagTerms();
            $this->log('Tags: '.count($tag_terms));

            $coupon_ids = self::getCouponIds();
            $this->log('Coupons: '.count($coupon_ids));

            $attribute_value_terms = self::getAttributeValueTerms();
            $this->log('Attribute values: '.count($attribute_value_terms));

            $attribute_value_attachment_ids = self::getAttributeValueAttachmentIds();
            $this->log('Attribute value attachments: '.count($attribute_value_attachment_ids));

            $attribute_ids = self::getAttributeIds();
            $this->log('Attributes: '.count($attribute_ids));

            $category_terms = self::getCategoryTerms();
            $this->log('Categories: '.count($category_terms));

            $category_attachment_ids = self::getCategoryAttachmentIds();
            $this->log('Category attachments: '.count($category_attachment_ids));

            $this->log('Products: '.count($this->productIds));
            $this->log('Products variations: '.count($this->productVariationIds));
            $this->log('Product images: '.count($this->productAttachmentIds));

            $order_ids = self::getOrderIds();
            $this->log('Orders (incl. internal): '.count($order_ids));

            $this->log('Tasks: '.TaskModel::count());

            $this->log('Web hook logs: '.WebhookLogModel::count());

            if (!array_key_exists('yes', $assoc_arguments)) {
                $this->line();
                $this->warning(
                    'You are about to delete all tags, coupons, attribute values, attributes, categories, products, orders, tasks and web hook logs in this environment.'
                );
                $this->warning('Make sure you have a working backup available.');
                $this->line();

                \WP_CLI::confirm('Are you sure you want to permanently delete all these entities?');
            }
        }

        $deletionCount = 1;
        $deletionTotal = 12;
        $logDeletion = function ($target) use (&$deletionCount, $deletionTotal) {
            $this->log("[$deletionCount / $deletionTotal] Deleting $target...");
            ++$deletionCount;
        };

        $this->line();

        if ($productsOnly) {
            $this->cleanProducts($logDeletion);
        } else {
            $logDeletion('tags');
            foreach ($tag_terms as $tag_term) {
                wp_delete_term($tag_term->term_id, $tag_term->taxonomy);
            }

            $logDeletion('coupons');
            foreach ($coupon_ids as $coupon_id) {
                wp_delete_post($coupon_id, true);
            }

            $logDeletion('attribute values');
            foreach ($attribute_value_terms as $attribute_value_term) {
                wp_delete_term($attribute_value_term->term_id, $attribute_value_term->taxonomy);
            }

            $logDeletion('attribute value images');
            foreach ($attribute_value_attachment_ids as $attribute_value_attachment_id) {
                wp_delete_attachment($attribute_value_attachment_id, true);
            }

            $logDeletion('attributes');
            foreach ($attribute_ids as $attribute_id) {
                wc_delete_attribute($attribute_id);
            }

            $logDeletion('categories');
            foreach ($category_terms as $category_term) {
                wp_delete_term($category_term->term_id, $category_term->taxonomy);
            }

            $logDeletion('category attachments');
            foreach ($category_attachment_ids as $category_attachment_id) {
                wp_delete_attachment($category_attachment_id, true);
            }

            $this->cleanProducts($logDeletion);

            $logDeletion('orders');
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                $order->delete(true);
            }

            $logDeletion('tasks');
            self::cleanTasks();

            $logDeletion('web hook logs');
            self::cleanWebhookLogs();
        }

        $this->line();
        $this->log('Verifying...');
        $this->line();

        $success = true;
        $countCheck = function ($target, $countable) use (&$success) {
            $count = count($countable);
            if (0 === $count) {
                $this->success("0 $target found");
            } else {
                $this->error("$count $target found", false);
                $success = false;
            }
        };

        if ($productsOnly) {
            $countCheck('products', self::getProductIds());
            $countCheck('product variations', self::getProductVariationIds());
            $countCheck('product images', self::getProductAttachmentIds());
        } else {
            $countCheck('tags', self::getTagTerms());
            $countCheck('coupons', self::getCouponIds());
            $countCheck('attribute values', self::getAttributeValueTerms());
            $countCheck('attribute value attachments', self::getAttributeValueAttachmentIds());
            $countCheck('attributes', self::getAttributeIds());
            $countCheck('categories', self::getCategoryTerms());
            $countCheck('category attachments', self::getCategoryAttachmentIds());
            $countCheck('products', self::getProductIds());
            $countCheck('product variations', self::getProductVariationIds());
            $countCheck('product images', self::getProductAttachmentIds());
            $countCheck('orders (incl. internal)', self::getOrderIds());

            $taskQuery = TaskModel::prepareQuery(TaskModel::getSelectHelper()->cols(['*']));
            $countCheck('tasks', $wpdb->get_results($taskQuery));

            $logsQuery = WebhookLogModel::prepareQuery(WebhookLogModel::getSelectHelper()->cols(['*']));
            $countCheck('web hook logs', $wpdb->get_results($logsQuery));
        }

        $this->line();

        if ($success) {
            $this->success('Done!');
        } else {
            $this->error('Not everything is deleted');
            exit(1);
        }
    }

    /**
     * @return array
     */
    public static function getProductIds()
    {
        return get_posts(
            [
                'numberposts' => -1,
                'fields' => 'ids',
                'post_type' => 'product',
                'post_status' => [
                    'publish',
                    'trash',
                    'draft',
                    'pending',
                ],
            ]
        );
    }

    /**
     * @return array
     */
    public static function getProductVariationIds()
    {
        return get_posts(
            [
                'numberposts' => -1,
                'fields' => 'ids',
                'post_type' => 'product_variation',
                'post_status' => [
                    'publish',
                    'trash',
                    'draft',
                    'pending',
                ],
            ]
        );
    }

    /**
     * @return array
     */
    public static function getProductAttachmentIds()
    {
        $attachmentIds = [];

        foreach (self::getProductIds() as $productId) {
            $product = WC()->product_factory->get_product($productId);
            if ($product) {
                $ids = array_merge([$product->get_image_id()], $product->get_gallery_image_ids());
                $ids = array_filter(
                    $ids,
                    function ($id) {
                        return !empty($id);
                    }
                ); // filter out empty ids;
                $attachmentIds = array_merge($attachmentIds, $ids);
            }
        }

        return $attachmentIds;
    }

    /**
     * @return array
     */
    public static function getOrderIds()
    {
        return get_posts(
            [
                'numberposts' => -1,
                'fields' => 'ids',
                'post_type' => wc_get_order_types(),
                'post_status' => array_keys(wc_get_order_statuses()),
            ]
        );
    }

    /**
     * @return array
     */
    public static function getAttributeIds()
    {
        return array_column(
            wc_get_attribute_taxonomies(),
            'attribute_id'
        );
    }

    /**
     * @return array|\WP_Error
     */
    public static function getAttributeValueTerms()
    {
        // Get only the product attributes;
        $attribute_taxonomies = array_filter(
            get_taxonomies(),
            function ($taxonomy) {
                return StringFunctions::startsWith($taxonomy, 'pa_');
            }
        );

        if ($attribute_taxonomies) {
            return get_terms(
                [
                    'taxonomy' => $attribute_taxonomies,
                    'hide_empty' => false,
                ]
            );
        } else {
            // attribute_taxonomies is empty, it shouldn't search for any terms in this case as it could find any false positives
            return [];
        }
    }

    public static function getAttributeValueAttachmentIds()
    {
        $attachmentIds = [];

        foreach (self::getAttributeValueTerms() as $attributeValueTerm) {
            $attachmentId = get_term_meta($attributeValueTerm->term_id, 'product_attribute_image', true);
            if (!empty($attachmentId)) {
                $attachmentIds[] = $attachmentId;
            }
        }

        return $attachmentIds;
    }

    /**
     * @return array
     */
    public static function getTagTerms()
    {
        return get_terms(
            [
                'taxonomy' => 'product_tag',
                'hide_empty' => false,
            ]
        );
    }

    /**
     * @return array
     */
    public static function getCategoryTerms()
    {
        return get_terms(
            [
                'taxonomy' => 'product_cat',
                'hide_empty' => false,
                'exclude' => [get_option('default_product_cat', 0)],
            ]
        );
    }

    /**
     * @return array
     */
    public static function getCategoryAttachmentIds()
    {
        $attachmentIds = [];

        foreach (self::getCategoryTerms() as $tagTerm) {
            $attachmentId = get_term_meta($tagTerm->term_id, 'thumbnail_id', true);
            if (!empty($attachmentId)) {
                $attachmentIds[] = $attachmentId;
            }
        }

        return $attachmentIds;
    }

    public static function cleanTasks()
    {
        global $wpdb;

        $delete = TaskModel::getDeleteHelper();

        return $wpdb->query(TaskModel::prepareQuery($delete));
    }

    public static function cleanWebhookLogs()
    {
        global $wpdb;

        $delete = WebhookLogModel::getDeleteHelper();

        return $wpdb->query(WebhookLogModel::prepareQuery($delete));
    }

    /**
     * @return array
     */
    public static function getCouponIds()
    {
        return get_posts(
            [
                'numberposts' => -1,
                'fields' => 'ids',
                'post_type' => 'shop_coupon',
                'post_status' => [
                    'publish',
                    'trash',
                ],
            ]
        );
    }

    protected function cleanProducts(\Closure $logDeletion): void
    {
        $logDeletion('product images');
        foreach ($this->productAttachmentIds as $productAttachmentId) {
            wp_delete_attachment($productAttachmentId, true);
        }

        $logDeletion('product variations');
        foreach ($this->productVariationIds as $productVariationId) {
            $productVariation = wc_get_product($productVariationId);
            $productVariation->delete(true);
        }

        $logDeletion('products');
        foreach ($this->productIds as $productId) {
            $product = wc_get_product($productId);
            $product->delete(true);
        }
    }
}
