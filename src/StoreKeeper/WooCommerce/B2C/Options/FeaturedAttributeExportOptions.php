<?php

namespace StoreKeeper\WooCommerce\B2C\Options;

use StoreKeeper\WooCommerce\B2C\Tools\Attributes;

class FeaturedAttributeExportOptions extends AbstractOptions
{
    private const FEATURED_PREFIX = 'featured_attribute_id';
    private const ATTRIBUTE_EXPORT_PREFIX = 'attribute_export';

    public static function getAttributeExportOptionConstant($alias): string
    {
        return self::getPrefixedConstant(self::ATTRIBUTE_EXPORT_PREFIX.'-'.$alias);
    }

    /// not export below

    /**
     * @param $alias
     */
    private static function getOptionName($alias): string
    {
        return self::FEATURED_PREFIX.'-'.$alias;
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
}
