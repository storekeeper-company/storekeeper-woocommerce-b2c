<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use Adbar\Dot;
use Exception;
use Psr\Log\LoggerAwareTrait;
use stdClass;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\AttributeTranslatorException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Imports\AttributeImport;
use StoreKeeper\WooCommerce\B2C\Imports\AttributeOptionImport;
use StoreKeeper\WooCommerce\B2C\Objects\PluginStatus;
use StoreKeeper\WooCommerce\B2C\Query\ProductQueryBuilder;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;
use function wc_create_attribute;
use function wc_get_attribute;
use WC_Product_Variation;
use function wc_update_attribute;

class Attributes
{
    use LoggerAwareTrait;

    const slug_prefix = 'sk_';

    const TYPE_TEXT = 'text';
    const TYPE_SELECT = 'select';

    const DEFAULT_ARCHIVED_SETTING = true;

    public static function getDefaultType()
    {
        // If the swatches plugin is active.
        if (PluginStatus::isEnabled(PluginStatus::WOO_VARIATION_SWATCHES)) {
            return self::TYPE_TEXT;
        }

        return self::TYPE_SELECT;
    }

    /**
     * @param int    $id   Id of the attribute option
     * @param string $name Name of the attribute
     *
     * @return string The sanitized option slug
     */
    public static function sanitizeOptionSlug($id, $name)
    {
        $wanted_prefix = self::slug_prefix.$id.'_';
        if (!StringFunctions::startsWith($name, $wanted_prefix)) {
            return $wanted_prefix.sanitize_title($name);
        } else {
            //is already sanitized
            //this function is called at a lot of places, in some cases it's called twice(+). (results in double prefixes etc)
            //rather than debugging or refactoring for hours we added this startsWith check here.
            return $name;
        }
    }

    private static function getProductAttributeOptionsMap(): array
    {
        $map = [];

        $next = true;
        $index = 0;
        while ($next) {
            $attributes = Attributes::getProductAttributesAtIndex($index++);
            $next = (bool) $attributes;

            if (is_array($attributes)) {
                foreach ($attributes as $attributeName => $attribute) {
                    if (0 === $attribute['is_taxonomy']) {
                        if (empty($map[$attributeName])) {
                            $attributeOptions = wc_get_text_attributes($attribute['value']);
                            $map[$attributeName] = count($attributeOptions) > 1;
                        }
                    }
                }
            }
        }

        return $map;
    }

    private static function getGenericAttributeOptionsMap(): array
    {
        $map = [];

        foreach (wc_get_attribute_taxonomies() as $taxonomy) {
            $name = wc_attribute_taxonomy_name($taxonomy->attribute_name);
            $map[$name] = true;
        }

        return $map;
    }

    /**
     * Returns a map with if the attribute have options or not.
     */
    public static function getAttributesWithOptionsMap(): array
    {
        return array_merge(
            self::getProductAttributeOptionsMap(),
            self::getGenericAttributeOptionsMap()
        );
    }

    private static function getGenericAttributes(): array
    {
        return array_map(
            function ($item) {
                return [
                    'id' => $item->attribute_id,
                    'name' => AttributeExport::getAttributeKey(
                        $item->attribute_name,
                        AttributeExport::TYPE_SYSTEM_ATTRIBUTE
                    ),
                    'label' => $item->attribute_label,
                    'options' => true,
                ];
            },
            wc_get_attribute_taxonomies()
        );
    }

    private static function getProductAttributes(): array
    {
        $attributeMap = [];

        $next = true;
        $index = 0;
        while ($next) {
            $attributes = Attributes::getProductAttributesAtIndex($index++);
            $next = (bool) $attributes;

            if (is_array($attributes)) {
                foreach ($attributes as $attributeName => $attribute) {
                    $attributeOptions = wc_get_text_attributes($attribute['value']);
                    if (0 === $attribute['is_taxonomy']) {
                        if (empty($attributeMap[$attributeName])) {
                            $attributeMap[$attributeName] = [
                                'id' => 0,
                                'name' => AttributeExport::getAttributeKey(
                                    $attributeName,
                                    AttributeExport::TYPE_CUSTOM_ATTRIBUTE
                                ),
                                'label' => $attribute['name'],
                                'options' => false,
                            ];
                        }

                        // Only check `options` it its false
                        if (!$attributeMap[$attributeName]['options']) {
                            $attributeMap[$attributeName]['options'] = count($attributeOptions) > 1;
                        }
                    }
                }
            }
        }

        return array_values($attributeMap);
    }

    public static function getAllAttributes(): array
    {
        return array_merge(
            self::getGenericAttributes(),
            self::getProductAttributes()
        );
    }

    protected function ensureAttribute($attribute_id)
    {
        // Check if attribute exists.
        $attribute = WooCommerceAttributeMetadata::listAttributesByMetadata(
            [
                'meta_key' => 'storekeeper_id',
                'meta_value' => $attribute_id,
                'single' => true,
            ]
        );

        // if not > import attribute
        if (false === $attribute) {
            $attributeImport = new AttributeImport(
                [
                    'storekeeper_id' => $attribute_id,
                ]
            );
            $attributeImport->setLogger($this->logger);
            self::throwExceptionArray($attributeImport->run());
        }
    }

    protected function ensureAttributeOptions($attribute_id, $attribute_option_ids = [])
    {
        if (count($attribute_option_ids) > 0) {
            $attribute = self::getAttribute($attribute_id);
            $attribute_slug = $attribute->slug;

            // Check if attribute options exists
            $missing_attribute_option_ids = [];

            // TODO [profes@3/14/19]: Improve this with a single SQL query
            foreach ($attribute_option_ids as $attribute_option_id) {
                // Add it when it can't find it.
                if (false === self::getAttributeOptionTermIdByAttributeOptionId(
                        $attribute_option_id,
                        $attribute_slug
                    )) {
                    $missing_attribute_option_ids[] = $attribute_option_id;
                }
            }

            // Import missing attribute options if there are any.
            if (count($missing_attribute_option_ids) > 0) {
                $data = ['attribute_option_ids' => $missing_attribute_option_ids];
                $attributeOption = new AttributeOptionImport($data);
                $attributeOption->setLogger($this->logger);
                self::throwExceptionArray($attributeOption->run());
            }
        }
    }

    public function ensureAttributeAndOptions($attribute_id, $attribute_option_ids = [])
    {
        $this->ensureAttribute($attribute_id);
        $this->ensureAttributeOptions($attribute_id, $attribute_option_ids);
    }

    public static function getAttributeOptionTermIdByAttributeOptionId($attribute_option_id, $attribute_name)
    {
        $args = [
            'hide_empty' => false, // also retrieve terms which are not used yet
            'meta_query' => [
                [
                    'key' => 'storekeeper_id',
                    'value' => $attribute_option_id,
                    'compare' => '=',
                ],
            ],
        ];

        // get_terms can return a wpError, which causes count below to error out.
        $terms = WordpressExceptionThrower::throwExceptionOnWpError(get_terms($args));

        if (count($terms) > 0) {
            $attribute_taxonomy = self::createWooCommerceAttributeName($attribute_name);

            $current = array_shift($terms);
            if ($current->taxonomy == $attribute_taxonomy) {
                return (int) $current->term_id;
            }
        }

        return false;
    }

    /**
     * @param $content_var
     *
     * @return mixed
     *
     * @throws Exception
     */
    public static function updateAttributeAndOptionFromContentVar($content_var)
    {
        $attribute_id = $content_var['attribute_id'];
        // Set the attributes' order/weight even if there is no attribute option id
        if (array_key_exists('attribute_order', $content_var)) {
            WooCommerceAttributeMetadata::setMetadata($attribute_id, 'attribute_order', $content_var['attribute_order']);
        }
        // If there is no attribute_options_id it should to be stored as an attribute within the system,
        // But it should be set on the product its self. so it can be skipped here.
        if (array_key_exists('attribute_option_id', $content_var)) {
            $attribute_slug = $content_var['name'];
            $attribute_name = substr($content_var['label'], 0, 30);

            $attribute_slug = self::createWooCommerceAttributeName($attribute_slug);

            self::updateAttribute(
                [
                    'attribute_id' => $attribute_id,
                    'slug' => $attribute_slug,
                    'name' => $attribute_name,
                ]
            );

            // Update attribute option.
            $attribute_option_id = $content_var['attribute_option_id'];
            $attribute_option_name = substr($content_var['value_label'], 0, 30);
            $attribute_option_slug = $content_var['value'];
            $attribute_option_image = array_key_exists('attribute_option_image_url', $content_var)
                ? $content_var['attribute_option_image_url'] : false;
            $attribute_option_order = array_key_exists('attribute_option_order', $content_var) ? $content_var['attribute_option_order'] : 0;

            return self::updateAttributeOption(
                $attribute_slug,
                $attribute_name,
                $attribute_option_id,
                $attribute_option_slug,
                $attribute_option_name,
                $attribute_option_image,
                $attribute_option_order,
            );
        }

        return false; // nothing was added
    }

    public static function updateAttribute($data = [])
    {
        if (
            !array_key_exists('attribute_id', $data) ||
            !array_key_exists('slug', $data) ||
            !array_key_exists('name', $data)
        ) {
            throw new Exception('Missing required data attribute_id, slug or name, has now only: '.json_encode($data));
        }

        $attributeCheck = self::getAttribute($data['attribute_id']);
        if (false === $attributeCheck) {
            $attributeCheck = self::getAttributeBySlug($data['slug']);
        }

        $isNew = !$attributeCheck;
        $data['name'] = substr($data['name'], 0, 30);

        if (!array_key_exists('type', $data)) {
            if ($isNew) {
                $data['type'] = 'text';
            } else {
                $data['type'] = $attributeCheck->type;
            }
        }

        if ($isNew) {
            if (!array_key_exists('has_archives', $data)) {
                $data['has_archives'] = self::DEFAULT_ARCHIVED_SETTING; // Activate archives when it is not set
            }

            // Create the attribute in woocommerce
            $attribute_id = WordpressExceptionThrower::throwExceptionOnWpError(wc_create_attribute($data));
        } else {
            $attribute = WordpressExceptionThrower::throwExceptionOnWpError(wc_get_attribute($attributeCheck->id));
            if ($attribute) {
                $data['has_archives'] = $attribute->has_archives;
            }

            // Update the attribute in woocommerce
            $attribute_id = WordpressExceptionThrower::throwExceptionOnWpError(
                wc_update_attribute($attributeCheck->id, $data)
            );
        }

        WooCommerceAttributeMetadata::setMetadata($attribute_id, 'storekeeper_id', $data['attribute_id']);
    }

    /**
     * @param $exceptions
     *
     * @return bool
     *
     * @throws Exception
     */
    protected static function throwExceptionArray($exceptions)
    {
        if (count($exceptions) <= 0) {
            return true;
        }

        $issues = array_map(
            function (Exception $exception) {
                return $exception->getMessage()."\r\n".$exception->getTraceAsString();
            },
            $exceptions
        );

        throw new Exception(join("\r\n", $issues));
    }

    /**
     * @param $attribute_id
     *
     * @return array<stdClass>|stdClass|false
     *
     * @throws Exception
     */
    public static function getAttribute($attribute_id)
    {
        return WooCommerceAttributeMetadata::listAttributesByMetadata(
            [
                'meta_key' => 'storekeeper_id',
                'meta_value' => $attribute_id,
                'single' => true,
            ]
        );
    }

    /**
     * @param $slug
     *
     * @return bool|stdClass|null
     */
    public static function getAttributeBySlug($slug)
    {
        global $wpdb;
        $slug = trim($slug);

        $sql = <<<SQL
SELECT attribute_id 
FROM `{$wpdb->prefix}woocommerce_attribute_taxonomies`
WHERE attribute_name=%s
SQL;

        $attribute_id = $wpdb->get_var($wpdb->prepare($sql, $slug));

        if (!$attribute_id) {
            return false;
        }

        return wc_get_attribute($attribute_id);
    }

    /**
     * @param $attribute_slug
     * @param $attribute_name
     *
     * @throws WordpressException
     */
    protected static function registerAttributeTemporary($attribute_slug, $attribute_name)
    {
        // Register as taxonomy while importing.
        $taxonomy_name = self::createWooCommerceAttributeName($attribute_slug);
        WordpressExceptionThrower::throwExceptionOnWpError(
            register_taxonomy(
                $taxonomy_name,
                apply_filters('woocommerce_taxonomy_objects_'.$taxonomy_name, ['product']),
                apply_filters(
                    'woocommerce_taxonomy_args_'.$taxonomy_name,
                    [
                        'labels' => [
                            'name' => $attribute_name,
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
     * @throws AttributeTranslatorException
     */
    public static function createWooCommerceAttributeName($tax_name)
    {
        try {
            // Get the correct attribute name
            $tax_name = AttributeTranslator::setTranslation($tax_name);
        } catch (\Throwable $throwable) {
            throw new AttributeTranslatorException($throwable->getMessage(), 0, $throwable);
        }

        // Change format when needed
        if (strlen($tax_name) > 32) {
            $tax_name = substr($tax_name, 0, 31);
        }
        if (!StringFunctions::startsWith($tax_name, 'pa_')) {
            $tax_name = wc_attribute_taxonomy_name($tax_name);
        }

        return $tax_name;
    }

    public static function getAttributeOptionTermId($attribute_name, $attribute_option_slug, $attribute_option_id)
    {
        $attribute_name = self::createWooCommerceAttributeName($attribute_name);
        $attribute_option_slug = self::sanitizeOptionSlug($attribute_option_id, $attribute_option_slug);

        global $wpdb;

        $sql = $wpdb->prepare(
            <<<SQL
SELECT terms.term_id 
FROM `{$wpdb->prefix}terms` AS terms
LEFT JOIN `{$wpdb->prefix}term_taxonomy` AS TAX 
ON terms.term_id=TAX.term_id 
WHERE terms.slug=%s
AND TAX.taxonomy=%s
LIMIT 1
SQL
            ,
            $attribute_option_slug,
            $attribute_name
        );

        return $wpdb->get_var($sql);
    }

    /**
     * @param $term_id
     * @param $image_url
     */
    public static function setAttributeOptionImage($term_id, $image_url)
    {
        // Import the image if the Swatches plugin is enabled
        // OR when the plugin is being tested.
        if (PluginStatus::isWoocommerceVariationSwatchesEnabled() || PluginStatus::isStoreKeeperSwatchesEnabled(
            ) || Core::isTest()) {
            if (Media::attachmentExists($image_url)) {
                $attachment = Media::getAttachment($image_url);
                $media_id = $attachment->ID;
            } else {
                $media_id = Media::createAttachment($image_url);
            }
            update_term_meta($term_id, 'product_attribute_image', $media_id);
        }
    }

    /**
     * @param $term_id
     */
    public static function unsetAttributeOptionImage($term_id)
    {
        if (PluginStatus::isWoocommerceVariationSwatchesEnabled() || PluginStatus::isStoreKeeperSwatchesEnabled()) {
            delete_term_meta($term_id, 'product_attribute_image'); // no image found > delete link
        }
    }

    /**
     * @param $attribute_slug
     * @param $attribute_name
     * @param $attribute_option_id
     * @param $attribute_option_slug
     * @param $attribute_option_name
     * @param $attribute_option_image
     *
     * @throws WordpressException
     */
    public static function updateAttributeOption(
        $attribute_slug,
        $attribute_name,
        $attribute_option_id,
        $attribute_option_slug,
        $attribute_option_name,
        $attribute_option_image,
        $attribute_option_order
    ) {
        self::registerAttributeTemporary($attribute_slug, $attribute_name);

        $attribute_option_term_id = self::getAttributeOptionTermId(
            $attribute_slug,
            $attribute_option_slug,
            $attribute_option_id
        );

        if (!$attribute_option_term_id) {
            $term = WordpressExceptionThrower::throwExceptionOnWpError(
                wp_insert_term(
                    $attribute_option_name,
                    self::createWooCommerceAttributeName($attribute_slug),
                    [
                        'slug' => self::sanitizeOptionSlug($attribute_option_id, $attribute_option_slug),
                    ]
                )
            );
            $term_id = $term['term_id'];
        } else {
            $term = WordpressExceptionThrower::throwExceptionOnWpError(
                wp_update_term(
                    $attribute_option_term_id,
                    self::createWooCommerceAttributeName($attribute_slug),
                    [
                        'name' => $attribute_option_name,
                        'slug' => self::sanitizeOptionSlug($attribute_option_id, $attribute_option_slug),
                    ]
                )
            );
            $term_id = $term['term_id'];
        }

        update_term_meta($term_id, 'storekeeper_id', $attribute_option_id);
        static::updateAttributeOptionOrder($term_id, $attribute_option_order);

        if ($attribute_option_image) {
            self::setAttributeOptionImage($term_id, $attribute_option_image);
        } else {
            self::unsetAttributeOptionImage($term_id);
        }

        return $term_id;
    }

    public static function updateAttributeOptionOrder(int $optionTermId, int $attributeOptionOrder): void
    {
        update_term_meta($optionTermId, 'order', $attributeOptionOrder);
    }

    public static function getProductAttributesAtIndex(int $index): ?array
    {
        global $wpdb;

        $query = ProductQueryBuilder::getProductAttributeArray($index);
        $results = $wpdb->get_results($query);
        if ($result = current($results)) {
            if ($attribute = unserialize($result->attributes)) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * @return int|mixed
     */
    public static function getOptionOrder(WC_Product_Variation $variation, Dot $associatedShopProduct): int
    {
        $attributes = $variation->get_attributes();

        try {
            $optionOrder = 0;
            $optionId = self::getAttributeOptionTermIdByAttributeOptionId(
                $associatedShopProduct->get('configurable_associated_product.attribute_option_ids')[0],
                key($attributes),
            );

            $optionMeta = get_term_meta($optionId);
            if (isset($optionMeta['order'])) {
                $optionOrder = $optionMeta['order'][0] ?? 0;
            }

            return $optionOrder;
        } catch (AttributeTranslatorException $attributeTranslatorException) {
            return 0;
        }
    }
}
