<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

class Base36Coder
{
    /**
     * @return string
     */
    public static function encode($string, string $prefix = '')
    {
        return $prefix.self::strBaseConvert(bin2hex($string), 16, 36);
    }

    /**
     * @return string
     */
    public static function decode($string, string $prefix = '')
    {
        return hex2bin(self::strBaseConvert(str_replace($prefix, '', $string), 36, 16));
    }

    /**
     * @param int $frombase
     * @param int $tobase
     *
     * @return int|string
     */
    protected static function strBaseConvert($str, $frombase = 10, $tobase = 36)
    {
        $str = trim($str);
        if (10 != intval($frombase)) {
            $len = strlen($str);
            $q = 0;
            for ($i = 0; $i < $len; ++$i) {
                $r = base_convert($str[$i], $frombase, 10);
                $q = bcadd(bcmul($q, $frombase), $r);
            }
        } else {
            $q = $str;
        }
        if (10 != intval($tobase)) {
            $s = '';
            while (bccomp($q, '0', 0) > 0) {
                $r = intval(bcmod($q, $tobase));
                $s = base_convert($r, 10, $tobase).$s;
                $q = bcdiv($q, $tobase, 0);
            }
        } else {
            $s = $q;
        }

        return $s;
    }
}
