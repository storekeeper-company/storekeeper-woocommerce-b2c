<?php

namespace StoreKeeper\WooCommerce\B2C\Tools\Export;

use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Tools\CommonAttributeName;
use StoreKeeper\WooCommerce\B2C\Tools\ProductAttributes;
use WC_Product_Attribute;

class AttributeExport
{
    protected static $exportAttributeToFeaturedAliasMap = null;

    public static function cleanCache()
    {
        self::$exportAttributeToFeaturedAliasMap = null;
    }

    public static function getAllAttributes(): array
    {
        return array_merge(
            self::getAttributes(),
            self::getCustomProductAttributes()
        );
    }

    public static function getAllNonFeaturedAttributes(): array
    {
        return array_merge(
            self::getAttributes(true),
            self::getCustomProductAttributes(true)
        );
    }

    protected static function getExportAttributeToFeaturedAliasMap(): array
    {
        if (is_null(self::$exportAttributeToFeaturedAliasMap)) {
            $map = [];
            foreach (FeaturedAttributeOptions::FEATURED_ATTRIBUTES_ALIASES as $alias) {
                $constant = FeaturedAttributeOptions::getAttributeExportOptionConstant($alias);
                $value = FeaturedAttributeOptions::get($constant);
                if (!empty($value)) {
                    $map[$value] = $alias;
                }
            }
            self::$exportAttributeToFeaturedAliasMap = $map;
        }

        return self::$exportAttributeToFeaturedAliasMap;
    }

    public static function isFeatured(string $exportName): bool
    {
        return !is_null(self::getFeaturedAlias($exportName));
    }

    public static function getFeaturedAlias(string $exportName): ?string
    {
        $featuredAttributes = self::getExportAttributeToFeaturedAliasMap();

        return $featuredAttributes[$exportName] ?? null;
    }

    public static function getCustomProductAttributes(bool $excludeFeatured = false): array
    {
        $attributeMap = [];
        foreach (ProductAttributes::getCustomProductAttributeOptions() as $attributeName => $attribute) {
            $attributeKey = self::getAttributeKey($attributeName, CommonAttributeName::TYPE_CUSTOM_ATTRIBUTE, $isFeatured);
            if (empty($attributeMap[$attributeKey])) {
                if (!$isFeatured || !$excludeFeatured) {
                    $attributeMap[$attributeKey] = [
                        'id' => 0,
                        'name' => $attributeKey,
                        'label' => $attribute['name'],
                        'options' => false,
                    ];
                }
            }
        }

        return array_values($attributeMap);
    }

    protected static function getAttributes(bool $excludeFeatured = false): array
    {
        $attributes = [];
        $items = wc_get_attribute_taxonomies();
        foreach ($items as $item) {
            $attributeKey = self::getAttributeKey($item->attribute_name, CommonAttributeName::TYPE_SYSTEM_ATTRIBUTE, $isFeatured);

            if (!$isFeatured || !$excludeFeatured) {
                $attributes[] = [
                    'id' => $item->attribute_id,
                    'name' => $attributeKey,
                    'label' => $item->attribute_label,
                    'options' => true,
                ];
            }
        }

        return $attributes;
    }

    public static function getAttributeOptions()
    {
        $attributes = wc_get_attribute_taxonomies();
        foreach ($attributes as $attribute) {
            $name = wc_attribute_taxonomy_name($attribute->attribute_name);
            $exportKey = AttributeExport::getAttributeKey(
                $attribute->attribute_name,
                CommonAttributeName::TYPE_SYSTEM_ATTRIBUTE
            );
            $featuredAlias = self::getFeaturedAlias($exportKey);
            if (empty($featuredAlias) || FeaturedAttributeOptions::isOptionsAttribute($featuredAlias)) {
                $attributeOptions = get_terms($name, ['hide_empty' => false]);
                foreach ($attributeOptions as $attributeOption) {
                    yield [
                        'name' => $attributeOption->slug,
                        'label' => $attributeOption->name,
                        'attribute_name' => $exportKey,
                        'attribute_label' => $attribute->attribute_label,
                    ];
                }
            }
        }
    }

    public static function getProductAttributeKey(WC_Product_Attribute $attribute): string
    {
        $type = self::getProductAttributeType($attribute);

        return self::getAttributeKey($attribute->get_name(), $type);
    }

    public static function getAttributeKey(string $attributeName, string $type, ?bool &$isFeatured = null): string
    {
        $attributeKey = CommonAttributeName::getName($attributeName, $type);
        $featuredName = self::getFeaturedAlias($attributeKey);
        $isFeatured = !empty($featuredName);
        if ($isFeatured) {
            return $featuredName;
        }

        return $attributeKey;
    }

    private static function getProductAttributeType(WC_Product_Attribute $attribute): string
    {
        return $attribute->get_id() <= 0 ? CommonAttributeName::TYPE_CUSTOM_ATTRIBUTE : CommonAttributeName::TYPE_SYSTEM_ATTRIBUTE;
    }


    public static function getProductAttributeOptions(WC_Product_Attribute $attribute): array
    {
        if ($attribute->get_id() <= 0) {
            return array_map(
                function ($name) {
                    return [
                        'alias' => sanitize_title($name),
                        'title' => $name,
                    ];
                },
                $attribute->get_options()
            );
        } else {
            return array_map(
                function ($term) {
                    return [
                        'alias' => $term->slug,
                        'title' => $term->name,
                    ];
                },
                get_terms($attribute->get_name(), ['hide_empty' => false])
            );
        }
    }

    public static function getProductAttributeLabel(WC_Product_Attribute $attribute): string
    {
        $label = $attribute->get_name();

        if ($attribute->get_id() > 0) {
            $label = wc_attribute_label($label);
        }

        return $label;
    }
}
