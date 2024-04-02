<?php

namespace StoreKeeper\WooCommerce\B2C\Options;

use StoreKeeper\WooCommerce\B2C\Tools\StringFunctions;

abstract class AbstractOptions
{
    public const PREFIX = 'storekeeper-woocommerce-b2c';

    /**
     * @param null $fallback
     *
     * @return mixed|null
     */
    public static function get($optionKey, $fallback = null)
    {
        if (self::exists($optionKey)) {
            return get_option(self::getPrefixedConstant($optionKey));
        }

        return $fallback;
    }

    /**
     * @return bool
     */
    public static function set($optionKey, $optionValue)
    {
        if (isset($optionValue)) {
            return update_option(self::getPrefixedConstant($optionKey), $optionValue);
        } else {
            return false;
        }
    }

    public static function getBoolOption(string $optionKey, ?bool $fallback = null): ?bool
    {
        if (self::exists($optionKey)) {
            return 'yes' === get_option(self::getPrefixedConstant($optionKey));
        }

        return $fallback;
    }

    public static function exists($optionKey)
    {
        $optionValue = get_option(self::getPrefixedConstant($optionKey));
        if (false !== $optionValue) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     *
     * @see OptionHelper::set
     */
    public static function update($optionKey, $optionValue)
    {
        return self::set($optionKey, $optionValue);
    }

    public static function delete($optionKey)
    {
        if (self::exists($optionKey)) {
            return delete_option(self::getPrefixedConstant($optionKey));
        }

        return false;
    }

    protected static function getPrefixedConstant($constant)
    {
        if (!StringFunctions::startsWith($constant, self::PREFIX)) {
            return self::PREFIX.'_'.$constant;
        } else {
            return $constant;
        }
    }

    public static function getConstant($constant)
    {
        return self::getPrefixedConstant($constant);
    }
}
