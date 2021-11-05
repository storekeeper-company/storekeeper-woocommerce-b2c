<?php

namespace StoreKeeper\WooCommerce\B2C\Options;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;

class FeaturedAttributeOptions extends AbstractOptions
{
    const FEATURED_PREFIX = 'featured_attribute_id';
    const ATTRIBUTE_EXPORT_PREFIX = 'attribute_export';

    const ALIAS_BRAND = 'brand';
    const ALIAS_BARCODE = 'barcode';
    const ALIAS_PRINTABLE_SHORTNAME = 'printable_shortname';
    const ALIAS_NEEDS_WEIGHT_ON_KASSA = 'needs_weight_on_kassa';
    const ALIAS_NEEDS_DESCRIPTION_ON_KASSA = 'needs_description_on_kassa';
    const ALIAS_MINIMAL_ORDER_QTY = 'minimal_order_qty';
    const ALIAS_IN_PACKAGE_QTY = 'in_package_qty';
    const ALIAS_IN_BOX_QTY = 'in_box_qty';
    const ALIAS_IN_OUTER_QTY = 'in_outer_qty';
    const ALIAS_UNIT_WEIGHT_IN_G = 'unit_weight_in_g';

    const FEATURED_ATTRIBUTES_ALIASES = [
        self::ALIAS_BRAND,
        self::ALIAS_BARCODE,
        self::ALIAS_PRINTABLE_SHORTNAME,
        self::ALIAS_NEEDS_WEIGHT_ON_KASSA,
        self::ALIAS_NEEDS_DESCRIPTION_ON_KASSA,
        self::ALIAS_UNIT_WEIGHT_IN_G,
        self::ALIAS_MINIMAL_ORDER_QTY,
        self::ALIAS_IN_PACKAGE_QTY,
        self::ALIAS_IN_BOX_QTY,
        self::ALIAS_IN_OUTER_QTY,
    ];

    public static function isOptionsAttribute(string $featured_alias)
    {
        return self::ALIAS_BRAND === $featured_alias;
    }

    public static function isBoolAttribute(string $featured_alias)
    {
        return self::ALIAS_NEEDS_WEIGHT_ON_KASSA === $featured_alias ||
            self::ALIAS_NEEDS_DESCRIPTION_ON_KASSA === $featured_alias;
    }

    public static function isIntAttribute(string $featured_alias)
    {
        return self::ALIAS_UNIT_WEIGHT_IN_G === $featured_alias ||
            self::ALIAS_MINIMAL_ORDER_QTY === $featured_alias ||
            self::ALIAS_IN_PACKAGE_QTY === $featured_alias ||
            self::ALIAS_IN_BOX_QTY === $featured_alias ||
            self::ALIAS_IN_OUTER_QTY === $featured_alias;
    }

    public static function getAttribute($alias)
    {
        $constant = self::getAttributeExportOptionConstant($alias);

        return self::get($constant);
    }

    /**
     * @param $alias
     */
    private static function getOptionName($alias): string
    {
        return self::FEATURED_PREFIX.'-'.$alias;
    }

    public static function getAttributeExportOptionConstant($alias): string
    {
        return self::getPrefixedConstant(self::ATTRIBUTE_EXPORT_PREFIX.'-'.$alias);
    }

    public static function getAliasName($alias)
    {
        switch ($alias) {
            case self::ALIAS_BRAND:
                return __('Brand', I18N::DOMAIN);
            case self::ALIAS_BARCODE:
                return __('Barcode', I18N::DOMAIN);
            case self::ALIAS_PRINTABLE_SHORTNAME:
                return __('Printable shortname', I18N::DOMAIN);
            case self::ALIAS_NEEDS_WEIGHT_ON_KASSA:
                return __('Needs weight on kassa', I18N::DOMAIN);
            case self::ALIAS_NEEDS_DESCRIPTION_ON_KASSA:
                return __('Needs description on kassa', I18N::DOMAIN);
            case self::ALIAS_MINIMAL_ORDER_QTY:
                return __('Minimal order quantity', I18N::DOMAIN);
            case self::ALIAS_IN_PACKAGE_QTY:
                return __('In package quantity', I18N::DOMAIN);
            case self::ALIAS_IN_BOX_QTY:
                return __('In box quantity', I18N::DOMAIN);
            case self::ALIAS_IN_OUTER_QTY:
                return __('In out quantity', I18N::DOMAIN);
            case self::ALIAS_UNIT_WEIGHT_IN_G:
                return __('Unit weight in grams', I18N::DOMAIN);
        }

        return $alias;
    }

    public static function deleteAttributes()
    {
        global $wpdb;
        $prefix = self::getPrefixedConstant(self::FEATURED_PREFIX);
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM $wpdb->options WHERE option_name like %s",
                $prefix.'%'
            )
        );
        foreach ($results as $result) {
            self::deleteAttribute(
                substr($result->option_name, strlen($prefix) + 1)
            );
        }
    }

    /**
     * @param $alias
     */
    public static function deleteAttribute($alias)
    {
        self::delete(self::getOptionName($alias));
    }

    /**
     * @param $alias
     */
    public static function setAttribute($alias, $attribute_id, $name)
    {
        self::set(
            self::getOptionName($alias),
            [
                'attribute_id' => $attribute_id,
                'attribute_name' => $name,
            ]
        );
    }

    public static function getWooCommerceAttributeName($alias)
    {
        $name = null;
        $option = self::get(self::getOptionName($alias));
        if (!empty($option)) {
            $name = Attributes::getAttributeSlug($option['attribute_id']);
            if (empty($name)) {
                // fallback to name, because woocommerce is not able to store attributes without options
                $name = $option['attribute_name'];
            }
        }

        return $name;
    }

    public static function getMappedFeaturedExportAttributes()
    {
        $map = [];

        foreach (FeaturedAttributeOptions::FEATURED_ATTRIBUTES_ALIASES as $alias) {
            $constant = FeaturedAttributeOptions::getAttributeExportOptionConstant($alias);
            $value = FeaturedAttributeOptions::get($constant);
            if (!empty($value)) {
                $map[$value] = $alias;
            }
        }

        return $map;
    }

    public static function isFeatured(string $exportName): bool
    {
        $featuredAttributes = FeaturedAttributeOptions::getMappedFeaturedExportAttributes();

        return array_key_exists($exportName, $featuredAttributes);
    }

    public static function getFeaturedNameIfPossible(string $exportName): string
    {
        $map = FeaturedAttributeOptions::getMappedFeaturedExportAttributes();
        if (array_key_exists($exportName, $map)) {
            return $map[$exportName];
        }

        return $exportName;
    }
}
