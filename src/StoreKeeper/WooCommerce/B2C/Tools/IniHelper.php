<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

class IniHelper
{
    private static $initialIni = [];

    public static function setIni(string $option, string $value, ?callable $onFailCallback = null)
    {
        static::saveInitialIni($option);

        self::doSetInit(
            $option,
            $value,
            $onFailCallback
        );
    }

    public static function getIni(string $option)
    {
        return @ini_get($option);
    }

    private static function saveInitialIni(string $option)
    {
        if (!array_key_exists($option, static::$initialIni)) {
            static::$initialIni[$option] = ini_get($option);
        }
    }

    public static function restoreInit($option, ?callable $onFailCallback = null)
    {
        if (array_key_exists($option, static::$initialIni)) {
            self::doSetInit(
                $option,
                static::$initialIni[$option],
                $onFailCallback
            );
        }
    }

    private static function doSetInit(string $option, string $value, ?callable $onFailCallback): void
    {
        if (false === @ini_set($option, $value) && is_callable($onFailCallback)) {
            call_user_func(
                $onFailCallback,
                sprintf(
                    __('Unable to set php configuration option %s'),
                    $option
                )
            );
        }
    }
}
