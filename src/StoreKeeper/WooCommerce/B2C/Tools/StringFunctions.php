<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

class StringFunctions
{
    /**
     * @return bool
     */
    public static function startsWith($haystack, $needle)
    {
        $length = strlen($needle);

        return substr($haystack, 0, $length) === $needle;
    }

    /**
     * @return bool
     */
    public static function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if (0 == $length) {
            return true;
        }

        return substr($haystack, -$length) === $needle;
    }

    public static function generateRandomHash($length = 64)
    {
        $random_bytes = openssl_random_pseudo_bytes($length / 2);

        return bin2hex($random_bytes);
    }

    public static function generateRandomString($length = 10)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }
}
