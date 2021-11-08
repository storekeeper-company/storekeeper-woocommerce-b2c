<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

class CommonAttributeOptionName
{
    public const PREFIX = 'sk_';

    public static function getName(string $attributeName, string $termName): string
    {
        $prefix = self::getPrefix($attributeName);
        if (StringFunctions::startsWith($termName, $prefix)) {
            return $termName; // assume it's already prefixed
        }

        return sanitize_title($prefix.$termName);
    }

    public static function cleanCommonNamePrefix(string $attributeName, string $termName): string
    {
        $prefix = self::getPrefix($attributeName);
        if (0 === strpos($termName, $prefix)) {
            return substr($termName, strlen($prefix));
        }

        return $termName;
    }

    protected static function getPrefix(string $attributeName): string
    {
        $attributeName = CommonAttributeName::cleanAttributeTermPrefix($attributeName);
        $attributeName = CommonAttributeName::cleanCommonNamePrefix($attributeName);
        $prefix = self::PREFIX.$attributeName.'_';

        return $prefix;
    }
}
