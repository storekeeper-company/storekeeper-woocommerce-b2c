<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

use DateTime;
use DateTimeZone;
use Exception;
use StoreKeeper\WooCommerce\B2C\I18N;

class DateTimeHelper
{
    const MYSQL_DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * Get current date and time
     * Don't use non-GMT time, unless you know the difference and really need to.
     *
     * @throws Exception
     */
    public static function currentDateTime($gmt = true): DateTime
    {
        $timezone = $gmt ? new DateTimeZone('UTC') : wp_timezone();

        return new DateTime('now', $timezone);
    }

    /**
     * Get current formatted date and time
     * Don't use non-GMT time, unless you know the difference and really need to.
     *
     * @throws Exception
     */
    public static function currentFormattedDateTime(string $format = self::MYSQL_DATE_FORMAT, $gmt = true): string
    {
        return self::currentDateTime($gmt)->format($format);
    }

    public static function dateDiff($date, $maximumInactiveMinutes = 15)
    {
        $mydate = date(DATE_RFC2822);

        $datetime1 = date_create($date);
        $datetime2 = date_create($mydate);
        $interval = date_diff($datetime1, $datetime2);

        $min = $interval->format('%i');
        $minInt = (int) $min;
        $hour = $interval->format('%h');
        $mon = $interval->format('%m');
        $day = $interval->format('%d');
        $year = $interval->format('%y');

        if ('00000' == $interval->format('%i%h%d%m%y')) {
            return false;
        } else {
            if ('0000' == $interval->format('%h%d%m%y') && $minInt < $maximumInactiveMinutes) {
                return false;
            } else {
                if ('0000' == $interval->format('%h%d%m%y')) {
                    return $min.' '.__('minutes', I18N::DOMAIN);
                } else {
                    if ('000' == $interval->format('%d%m%y')) {
                        return $hour.' '.__('hours', I18N::DOMAIN);
                    } else {
                        if ('00' == $interval->format('%m%y')) {
                            return $day.' '.__('days', I18N::DOMAIN);
                        } else {
                            if ('0' == $interval->format('%y')) {
                                return $mon.' '.__('months', I18N::DOMAIN);
                            } else {
                                return $year.' '.__('years', I18N::DOMAIN);
                            }
                        }
                    }
                }
            }
        }
    }
}
