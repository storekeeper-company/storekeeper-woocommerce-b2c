<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

use StoreKeeper\WooCommerce\B2C\I18N;

class DateTimeHelper
{
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
