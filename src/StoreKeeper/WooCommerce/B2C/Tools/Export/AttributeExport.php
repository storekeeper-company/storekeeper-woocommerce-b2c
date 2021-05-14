<?php

namespace StoreKeeper\WooCommerce\B2C\Tools\Export;

use WC_Product_Attribute;

class AttributeExport
{
    const TYPE_CUSTOM_ATTRIBUTE = 'type_custom_attribute';
    const TYPE_SYSTEM_ATTRIBUTE = 'type_system_attribute';

    public static function getProductAttributeKey(WC_Product_Attribute $attribute): string
    {
        $name = self::cleanName($attribute->get_name());

        $type = self::getProductAttributeType($attribute);
        $prefix = self::getPrefix($type);

        return sanitize_title($prefix.'_'.$name);
    }

    public static function getAttributeKey(string $attributeName, string $type): string
    {
        $name = self::cleanName($attributeName);
        $prefix = self::getPrefix($type);

        return sanitize_title($prefix.'_'.$name);
    }

    private static function getProductAttributeType(WC_Product_Attribute $attribute): string
    {
        return $attribute->get_id() <= 0 ? self::TYPE_CUSTOM_ATTRIBUTE : self::TYPE_SYSTEM_ATTRIBUTE;
    }

    private static function cleanName(string $name): string
    {
        $name = str_replace('attribute_', '', $name);
        $name = str_replace('pa_', '', $name);

        return $name;
    }

    /**
     * We add a prefix to the name based on if the attribute:
     * Is a custom attribute for that product (ca-)
     * Is a system attribute for the whole system (sa-).
     */
    private static function getPrefix(string $type): string
    {
        switch ($type) {
            case self::TYPE_SYSTEM_ATTRIBUTE:
                return 'sa';
            case self::TYPE_CUSTOM_ATTRIBUTE:
            default:
                return 'ca';
        }
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
