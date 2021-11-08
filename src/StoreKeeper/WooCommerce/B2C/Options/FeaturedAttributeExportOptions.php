<?php

namespace StoreKeeper\WooCommerce\B2C\Options;

class FeaturedAttributeExportOptions extends AbstractOptions
{
    private const ATTRIBUTE_EXPORT_PREFIX = 'attribute_export';

    public static function getAttributeExportOptionConstant($alias): string
    {
        return self::getPrefixedConstant(self::ATTRIBUTE_EXPORT_PREFIX.'-'.$alias);
    }
}
