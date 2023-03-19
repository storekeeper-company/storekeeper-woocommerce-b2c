<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use stdClass;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeOptionModel;
use StoreKeeper\WooCommerce\B2C\Objects\PluginStatus;
use function wc_create_attribute;
use function wc_get_attribute;
use function wc_update_attribute;

class Attributes
{
    use LoggerAwareTrait;

    private const slug_prefix = 'sk_';

    private const TYPE_TEXT = 'text';
    public const TYPE_SELECT = 'select';

    public const TYPE_DEFAULT = self::TYPE_SELECT;

    const DEFAULT_ARCHIVED_SETTING = true;
    const MAX_NAME_LENGTH = 200;
    const TAXONOMY_MAX_LENGTH = 25; // 28 - 3 (pa_ prefix)

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    protected static function getDefaultType()
    {
        // If the swatches plugin is active.
        if (PluginStatus::isEnabled(PluginStatus::WOO_VARIATION_SWATCHES)) {
            return self::TYPE_TEXT;
        }

        return self::TYPE_SELECT;
    }

    public static function getAttributeOptionsMap(): array
    {
        $map = [];

        foreach (wc_get_attribute_taxonomies() as $taxonomy) {
            $name = wc_attribute_taxonomy_name($taxonomy->attribute_name);
            $map[$name] = true;
        }

        return $map;
    }

    protected static function getUnmatchedAttributes(): array
    {
        $attributes = wc_get_attribute_taxonomies();
        $sk_attribute_ids = AttributeModel::getAttributeIds();
        $unmatched_attributes = [];
        foreach ($attributes as $attribute) {
            if (in_array($attribute->attribute_id, $sk_attribute_ids)) {
                continue; //already matched
            }
            $unmatched_attributes[] = $attribute;
        }

        return $unmatched_attributes;
    }

    /**
     * @return array<int,int> [storekeeper_id => wc_attribute_id, ... ]
     *
     * @throws WordpressException
     */
    public static function importsAttributes(array $sk_attributes): array
    {
        $attribute_sk_to_wc = [];
        foreach ($sk_attributes as $sk_attribute) {
            $attribute_sk_to_wc[$sk_attribute['id']] = self::importAttribute(
                $sk_attribute['id'],
                $sk_attribute['name'],
                $sk_attribute['label'],
            );
        }

        return $attribute_sk_to_wc;
    }

    /**
     * @param array<int,int> $attribute_sk_to_wc [storekeeper_id => wc_attribute_id, ... ] see self::importsAttributes
     *
     * @return array<int,int> [storekeeper_id => term_id, ... ]
     *
     * @throws WordpressException
     */
    public static function importsAttributeOptions(array $attribute_sk_to_wc, array $sk_options): array
    {
        $option_sk_to_wc = [];
        foreach ($sk_options as $sk_option) {
            $attribute_id = $attribute_sk_to_wc[$sk_option['attribute_id']];
            $attributeImage = $sk_option['image_url'] ?? null;
            $option_sk_to_wc[$sk_option['id']] = self::importAttributeOption(
                $attribute_id,
                $sk_option['id'],
                $sk_option['name'],
                $sk_option['label'],
                $attributeImage,
                $sk_option['order'] ?? 0
            );
        }

        return $option_sk_to_wc;
    }

    protected static function getAttributeOptionTermIdByAttributeOptionIdInMeta(int $sk_attribute_option_id, string $attribute_taxonomy_name): ?int
    {
        $args = [
            'taxonomy' => $attribute_taxonomy_name,
            'hide_empty' => false, // also retrieve terms which are not used yet on products
            'meta_query' => [
                [
                    'key' => 'storekeeper_id',
                    'value' => $sk_attribute_option_id,
                    'compare' => '=',
                ],
            ],
            'fields' => 'ids',
        ];

        // get_terms can return a wpError, which causes count below to error out.
        $terms = WordpressExceptionThrower::throwExceptionOnWpError(get_terms($args));

        if (count($terms) > 0) {
            return array_shift($terms)->term_id;
        }

        return null;
    }

    public static function isAttributeLinkedToBackend(int $attribute_id)
    {
        $attribute = self::getAttribute($attribute_id);

        return !empty($attribute);
    }

    public static function getAttributeSlug(int $attribute_id): ?string
    {
        $attribute = self::getAttribute($attribute_id);

        if (!empty($attribute)) {
            return $attribute->slug;
        }

        return null;
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

    public static function getAttribute($storekeeper_id): ?stdClass
    {
        return AttributeModel::getAttributeByStoreKeeperId($storekeeper_id);
    }

    /**
     * @param $slug
     *
     * @return bool|stdClass|null
     */
    protected static function findMatchingAttributeOption(
        string $attribute_alias,
        string $alias,
        string $title
    ): ?int {
        $term_id = null;

        // search by slug
        $cleanedSlug = CommonAttributeOptionName::cleanCommonNamePrefix($attribute_alias, $alias);
        $name_options = array_unique([
            $alias,
            $cleanedSlug,
            sanitize_title($alias),
            sanitize_title($cleanedSlug),
        ]);
        foreach ($name_options as $name_option) {
            $args = [
                'taxonomy' => $attribute_alias,
                'hide_empty' => false, // also retrieve terms which are not used yet on products
                'slug' => $name_option,
                'fields' => 'ids',
            ];
            $term_ids = WordpressExceptionThrower::throwExceptionOnWpError(get_terms($args));
            foreach ($term_ids as $possible_term_id) {
                if (!AttributeOptionModel::termIdExists($possible_term_id)) {
                    $term_id = $possible_term_id;
                    break 2;
                }
            }
        }

        if (empty($term_id)) {
            // search by exact label
            $args = [
                'taxonomy' => $attribute_alias,
                'hide_empty' => false, // also retrieve terms which are not used yet on products
                'name' => $title,
                'fields' => 'ids',
            ];
            $term_ids = WordpressExceptionThrower::throwExceptionOnWpError(get_terms($args));
            foreach ($term_ids as $possible_term_id) {
                if (!AttributeOptionModel::termIdExists($possible_term_id)) {
                    $term_id = $possible_term_id;
                    break;
                }
            }
        }

        return $term_id;
    }

    protected static function findMatchingAttribute(
        string $alias,
        string $title
    ): ?stdClass {
        $attribute_id = null;
        $unmatched_attributes = self::getUnmatchedAttributes();
        // match by alias
        $cleanedSlug = CommonAttributeName::cleanCommonNamePrefix($alias);
        $name_options = array_unique([
            $alias,
            $cleanedSlug,
            sanitize_title($alias),
            sanitize_title($cleanedSlug),
        ]);
        foreach ($name_options as $name_option) {
            foreach ($unmatched_attributes as $attribute) {
                if ($attribute->attribute_name === $name_option) {
                    $attribute_id = $attribute->attribute_id;
                    break 2;
                }
            }
        }

        // match by label
        if (empty($attribute_id)) {
            $sanitized_label = sanitize_title($title);
            foreach ($unmatched_attributes as $attribute) {
                if (sanitize_title($attribute->attribute_label) === $sanitized_label) {
                    $attribute_id = $attribute->attribute_id;
                    break;
                }
            }
        }

        if (!empty($attribute_id)) {
            return wc_get_attribute($attribute_id);
        }

        return null;
    }

    /**
     * registers the taxonomy, so we can fo wp_query on it.
     */
    protected static function registerAttributeTemporary($taxonomy_name, $label)
    {
        // Register as taxonomy while importing.
        WordpressExceptionThrower::throwExceptionOnWpError(
            register_taxonomy(
                $taxonomy_name,
                apply_filters('woocommerce_taxonomy_objects_'.$taxonomy_name, ['product']),
                apply_filters(
                    'woocommerce_taxonomy_args_'.$taxonomy_name,
                    [
                        'labels' => [
                            'name' => $label,
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
     * @param $term_id
     * @param $image_url
     */
    protected static function setAttributeOptionImage($term_id, $image_url)
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
    protected static function unsetAttributeOptionImage($term_id)
    {
        if (PluginStatus::isWoocommerceVariationSwatchesEnabled() || PluginStatus::isStoreKeeperSwatchesEnabled()) {
            delete_term_meta($term_id, 'product_attribute_image'); // no image found > delete link
        }
    }

    public static function importAttributeOption(
        int $attribute_id,
        int $sk_attribute_option_id,
        string $option_alias,
        string $option_name,
        ?string $option_image = null,
        int $option_order = 0
    ): int {
        $wc_attribute = wc_get_attribute($attribute_id);
        self::registerAttributeTemporary($wc_attribute->slug, $wc_attribute->name);

        $term_id = AttributeOptionModel::getTermIdByStorekeeperId(
            $attribute_id,
            $sk_attribute_option_id
        );
        $by_meta = false;
        if (empty($term_id)) {
            // try to find by term_meta (version before 7.4.0 was storing it that way)
            $term_id = self::getAttributeOptionTermIdByAttributeOptionIdInMeta(
                $sk_attribute_option_id,
                $wc_attribute->slug
            );
            $by_meta = true;
        }
        if (empty($term_id)) {
            // seems like option was deleted and recreated after (alias cannot be changed)
            $term_id = AttributeOptionModel::getTermIdByStorekeeperAlias(
                $attribute_id,
                $option_alias
            );
        }
        if (empty($term_id)) {
            $term_id = self::findMatchingAttributeOption(
                $wc_attribute->slug,
                $option_alias,
                $option_name
            );
        }

        // Update attribute option.
        $option_name = substr($option_name, 0, self::MAX_NAME_LENGTH);
        if (!$term_id) {
            $option_alias = CommonAttributeOptionName::getName(
                $wc_attribute->slug, $option_alias
            );
            $option_alias = wp_unique_term_slug($option_alias, (object) [
                'taxonomy' => $wc_attribute->slug,
            ]);
            $term = WordpressExceptionThrower::throwExceptionOnWpError(
                wp_insert_term(
                    $option_name,
                    $wc_attribute->slug,
                    [
                        'slug' => $option_alias,
                    ]
                )
            );
            $term_id = $term['term_id'];
        } else {
            WordpressExceptionThrower::throwExceptionOnWpError(
                wp_update_term(
                    $term_id,
                    $wc_attribute->slug,
                    [
                        'name' => $option_name,
                    ]
                )
            );
        }

        update_term_meta($term_id, 'order', $option_order);

        if ($option_image) {
            self::setAttributeOptionImage($term_id, $option_image);
        } else {
            self::unsetAttributeOptionImage($term_id);
        }

        AttributeOptionModel::setAttributeOptionTerm(
            WordpressExceptionThrower::throwExceptionOnWpError(get_term($term_id)),
            $attribute_id,
            $sk_attribute_option_id,
            $option_alias
        );

        if ($by_meta) {
            // clean the old way of getting the option <7.4.0
            delete_term_meta(
                $term_id,
                'storekeeper_id'
            );
        }

        return $term_id;
    }

    /**
     * @return int attribute id
     *
     * @throws WordpressException
     */
    public static function importAttribute(
        int $storekeeper_id,
        string $alias,
        string $title
    ): int {
        $existingAttribute = self::getAttribute($storekeeper_id);
        if (empty($existingAttribute)) {
            // maybe the attribute was deleted on the StoreKeper site and recreated (alias cannot be changed)
            $existingAttribute = AttributeModel::getAttributeByStoreKeeperAlias($alias);
        }
        if (empty($existingAttribute)) {
            $existingAttribute = self::findMatchingAttribute($alias, $title);
        }

        if (empty(trim($title))) {
            // fallback in case if empty
            $title = $alias;
        }

        $update_arguments = [
            'name' => substr($title, 0, self::MAX_NAME_LENGTH),
        ];

        if (empty($existingAttribute)) {
            // Create the attribute in woocommerce
            $update_arguments['type'] = self::getDefaultType();
            $update_arguments['slug'] = self::prepareNewAttributeSlug($alias);
            $update_arguments['has_archives'] = self::DEFAULT_ARCHIVED_SETTING;
            $attribute_id = WordpressExceptionThrower::throwExceptionOnWpError(
                wc_create_attribute($update_arguments)
            );

            $wcAttribute = WordpressExceptionThrower::throwExceptionOnWpError(
                wc_get_attribute($attribute_id)
            );

            self::registerAttributeTemporary(CommonAttributeName::cleanAttributeTermPrefix($wcAttribute->slug), $wcAttribute->name);
        } else {
            // Update old attribute (title)
            $update_arguments['type'] = $existingAttribute->type;
            $update_arguments['slug'] = $existingAttribute->slug;
            $update_arguments['has_archives'] = $existingAttribute->has_archives;

            $attribute_id = WordpressExceptionThrower::throwExceptionOnWpError(
                wc_update_attribute($existingAttribute->id, $update_arguments)
            );

            // Manually update attribute as wc_update_attribute reverts
            // attribute type to select when it does not exist
            // Initially the fix was setting WP_ADMIN to true due to woocommerce-swatch, then it breaks
            self::updateAttributeType($existingAttribute);
        }

        AttributeModel::setAttributeStoreKeeperId(
            $attribute_id, $storekeeper_id, $alias
        );

        return $attribute_id;
    }

    protected static function updateAttributeType(stdClass $existingAttribute): bool
    {
        global $wpdb;

        $updated = $wpdb->update($wpdb->prefix.'woocommerce_attribute_taxonomies', [
            'attribute_type' => $existingAttribute->type,
        ], [
            'attribute_id' => $existingAttribute->id,
        ]);

        return false !== $updated;
    }

    protected static function prepareNewAttributeSlug(string $slug): string
    {
        $clean_slug_base = wc_sanitize_taxonomy_name(CommonAttributeName::cleanAttributeTermPrefix($slug));
        $clean_slug_base = substr($clean_slug_base, 0, self::TAXONOMY_MAX_LENGTH);
        $i = 1;
        $clean_slug = $clean_slug_base;
        while (
            wc_check_if_attribute_name_is_reserved($clean_slug) ||
            taxonomy_exists($clean_slug) // taxonomy exists
        ) {
            $suffix = '_'.$i;
            $len = self::TAXONOMY_MAX_LENGTH - strlen($suffix);
            $clean_slug = substr($clean_slug_base, 0, $len).$suffix;
            ++$i;
        }

        return $clean_slug;
    }
}
