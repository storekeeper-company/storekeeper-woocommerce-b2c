<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\I18N;

class FeaturedAttributes
{
    public const ALIAS_BARCODE = 'barcode';
    public const ALIAS_BRAND = 'brand';
    public const ALIAS_SEASON = 'season';
    public const ALIAS_PRINTABLE_SHORTNAME = 'printable_shortname';
    public const ALIAS_NEEDS_WEIGHT_ON_KASSA = 'needs_weight_on_kassa';
    public const ALIAS_NEEDS_DESCRIPTION_ON_KASSA = 'needs_description_on_kassa';
    public const ALIAS_DURATION_IN_SECONDS = 'duration_in_seconds';
    public const ALIAS_CONDITION = 'condition';

    public const ALIAS_MINIMAL_ORDER_QTY = 'minimal_order_qty';
    public const ALIAS_IN_PACKAGE_QTY = 'in_package_qty';
    public const ALIAS_IN_BOX_QTY = 'in_box_qty';
    public const ALIAS_IN_OUTER_QTY = 'in_outer_box_qty';
    public const ALIAS_SALES_UNIT = 'sales_unit';
    public const ALIAS_UNIT_WEIGHT_IN_G = 'unit_weight_in_g';

    public const ALL_FEATURED_ALIASES = [
        self::ALIAS_BARCODE,
        self::ALIAS_BRAND,
        self::ALIAS_SEASON,
        self::ALIAS_PRINTABLE_SHORTNAME,
        self::ALIAS_NEEDS_DESCRIPTION_ON_KASSA,
        self::ALIAS_NEEDS_WEIGHT_ON_KASSA,
        self::ALIAS_DURATION_IN_SECONDS,
        self::ALIAS_CONDITION,
        self::ALIAS_MINIMAL_ORDER_QTY,
        self::ALIAS_IN_PACKAGE_QTY,
        self::ALIAS_IN_BOX_QTY,
        self::ALIAS_IN_OUTER_QTY,
        self::ALIAS_SALES_UNIT,
        self::ALIAS_UNIT_WEIGHT_IN_G,
    ];

    public static function isOptionsAttribute(string $featured_alias)
    {
        return self::ALIAS_BRAND === $featured_alias;
    }

    public static function isBoolAttribute(string $featured_alias)
    {
        return self::ALIAS_NEEDS_WEIGHT_ON_KASSA === $featured_alias
            || self::ALIAS_NEEDS_DESCRIPTION_ON_KASSA === $featured_alias;
    }

    public static function isIntAttribute(string $featured_alias)
    {
        return self::ALIAS_UNIT_WEIGHT_IN_G === $featured_alias
            || self::ALIAS_MINIMAL_ORDER_QTY === $featured_alias
            || self::ALIAS_IN_PACKAGE_QTY === $featured_alias
            || self::ALIAS_IN_BOX_QTY === $featured_alias
            || self::ALIAS_IN_OUTER_QTY === $featured_alias;
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
            case self::ALIAS_SEASON:
                return __('Season', I18N::DOMAIN);
            case self::ALIAS_DURATION_IN_SECONDS:
                return __('Duration in seconds', I18N::DOMAIN);
            case self::ALIAS_CONDITION:
                return __('Condition', I18N::DOMAIN);
            case self::ALIAS_SALES_UNIT:
                return __('Sales unit', I18N::DOMAIN);
        }

        return $alias;
    }
}
