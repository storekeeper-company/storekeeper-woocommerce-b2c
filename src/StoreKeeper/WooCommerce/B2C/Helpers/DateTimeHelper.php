<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

use StoreKeeper\WooCommerce\B2C\I18N;

class DateTimeHelper
{
    public const MYSQL_DATETIME_FORMAT = 'Y-m-d H:i:s';
    public const MYSQL_DATE_FORMAT = 'Y-m-d';
    public const WORDPRESS_DATE_FORMAT_OPTION = 'date_format';
    public const WORDPRESS_TIME_FORMAT_OPTION = 'time_format';

    /**
     * Get current date and time
     * Don't use non-GMT time, unless you know the difference and really need to.
     *
     * @throws \Exception
     */
    public static function currentDateTime(): \DateTime
    {
        return new \DateTime('now', new \DateTimeZone('UTC'));
    }

    public static function formatForStorekeeperApi(?\DateTime $dateTime = null): string
    {
        if (is_null($dateTime)) {
            $dateTime = self::currentDateTime();
        }

        return $dateTime->setTimezone(wp_timezone())->format('c');
    }

    public static function formatForDisplay(?\DateTime $dateTime = null): string
    {
        if (is_null($dateTime)) {
            return '-';
        }

        $dateFormat = get_option(self::WORDPRESS_DATE_FORMAT_OPTION);
        $timeFormat = get_option(self::WORDPRESS_TIME_FORMAT_OPTION);

        if (!$dateFormat) {
            $dateFormat = 'F j, Y';
        }

        if (!$timeFormat) {
            $timeFormat = 'g:i a';
        }

        return $dateTime->setTimezone(wp_timezone())->format("$dateFormat $timeFormat");
    }

    public static function dateDiff(\DateTime $datetime1, $maximumInactiveMinutes = 15)
    {
        $mydate = date(DATE_RFC2822);

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
