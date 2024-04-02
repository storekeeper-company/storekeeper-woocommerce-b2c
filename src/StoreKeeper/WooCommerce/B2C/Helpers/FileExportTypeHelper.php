<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

use StoreKeeper\WooCommerce\B2C\FileExport\AllFileExport;
use StoreKeeper\WooCommerce\B2C\FileExport\AttributeFileExport;
use StoreKeeper\WooCommerce\B2C\FileExport\AttributeOptionsFileExport;
use StoreKeeper\WooCommerce\B2C\FileExport\CategoryFileExport;
use StoreKeeper\WooCommerce\B2C\FileExport\CustomerFileExport;
use StoreKeeper\WooCommerce\B2C\FileExport\ProductBlueprintFileExport;
use StoreKeeper\WooCommerce\B2C\FileExport\ProductFileExport;
use StoreKeeper\WooCommerce\B2C\FileExport\TagFileExport;
use StoreKeeper\WooCommerce\B2C\I18N;

final class FileExportTypeHelper
{
    public const CATEGORY = 'category-export';
    public const TAG = 'tag-export';
    public const ATTRIBUTE = 'attribute-export';
    public const ATTRIBUTE_OPTION = 'attribute-option-export';
    public const ATTRIBUTE_SET = 'attribute-set-export';
    public const PRODUCT = 'product-export';
    public const PRODUCT_BLUEPRINT = 'product-blueprint-export';
    public const CUSTOMER = 'customer-export';

    public const ALL = 'all';

    public const TYPES = [
        self::CATEGORY,
        self::TAG,
        self::ATTRIBUTE,
        self::ATTRIBUTE_OPTION,
        self::ATTRIBUTE_SET,
        self::CUSTOMER,
        self::PRODUCT,
        self::PRODUCT_BLUEPRINT,
        self::ALL,
    ];

    public const CLASS_MAP = [
        self::CATEGORY => CategoryFileExport::class,
        self::TAG => TagFileExport::class,
        self::ATTRIBUTE => AttributeFileExport::class,
        self::ATTRIBUTE_OPTION => AttributeOptionsFileExport::class,
        self::CUSTOMER => CustomerFileExport::class,
        self::PRODUCT => ProductFileExport::class,
        self::PRODUCT_BLUEPRINT => ProductBlueprintFileExport::class,
        self::ALL => AllFileExport::class,
    ];

    public static function ensureType(string $type)
    {
        if (empty(self::CLASS_MAP[$type])) {
            throw new \Exception("Unknown export type $type");
        }
    }

    public static function getTypePluralName(string $type)
    {
        switch ($type) {
            case self::CATEGORY:
                return __('Categories', I18N::DOMAIN);
            case self::ATTRIBUTE:
                return __('Attributes', I18N::DOMAIN);
            case self::ATTRIBUTE_OPTION:
                return __('Attribute options', I18N::DOMAIN);
            case self::CUSTOMER:
                return __('Customers', I18N::DOMAIN);
            case self::PRODUCT:
                return __('Products', I18N::DOMAIN);
            case self::PRODUCT_BLUEPRINT:
                return __('Product blueprints', I18N::DOMAIN);
            case self::TAG:
                return __('Tags', I18N::DOMAIN);
            case self::ALL:
                return __('All', I18N::DOMAIN);
            default:
                return __($type, I18N::DOMAIN);
        }
    }

    /**
     * @throws \Exception
     */
    public static function getClass(string $type): string
    {
        self::ensureType($type);

        return self::CLASS_MAP[$type];
    }
}
