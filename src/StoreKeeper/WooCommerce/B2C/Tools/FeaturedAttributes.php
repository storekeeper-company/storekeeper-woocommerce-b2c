<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\I18N;

class FeaturedAttributes
{
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

    const ALL_ALIASES = [
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
}
