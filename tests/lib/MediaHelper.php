<?php

namespace StoreKeeper\WooCommerce\B2C\TestLib;

use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;

class MediaHelper
{
    /**
     * @var string
     */
    public static $mockDirectory;

    public static function getMediaPath($url)
    {
        if (!MediaHelper::$mockDirectory) {
            throw new BaseException(__CLASS__.'::$mockDirectory is not set');
        }

        $target = self::$mockDirectory.'/'.basename(parse_url($url)['path']);

        return $target;
    }
}
