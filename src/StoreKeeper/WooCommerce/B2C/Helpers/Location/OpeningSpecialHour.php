<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers\Location;

use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningSpecialHoursModel;
use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;
use StoreKeeper\WooCommerce\B2C\I18N;

class OpeningSpecialHour
{

    /**
     * Render opening special hour item
     *
     * @param int|array|object $openingSpecialHour
     * @return bool|string|void
     */
    public static function renderItem($openingSpecialHour, $args = [])
    {
        $defaults = [
            'format' => '<li class="%1$s"><strong>%2$s</strong>: %3$s</li>',
            'item_class' => 'opening-special-hour',
            'echo' => true
        ];
        $args = wp_parse_args($args, $defaults);

        if (is_numeric($openingSpecialHour)) {
            $openingSpecialHour = OpeningSpecialHoursModel::get((int) $openingSpecialHour);

            if ($args['echo']) {
                return;
            }

            if (!$openingSpecialHour) {
                return false;
            }
        }

        if (is_object($openingSpecialHour) && ($openingSpecialHour instanceof \stdClass ||
            method_exists($openingSpecialHour, '__toArray'))) {
            $openingSpecialHour = (array) $openingSpecialHour;
        }

        if (!is_array($openingSpecialHour)) {
            if ($args['echo']) {
                return;
            }

            return false;
        }

        try {
            OpeningSpecialHoursModel::validateData($openingSpecialHour);

            $dateFormat = get_option(DateTimeHelper::WORDPRESS_DATE_FORMAT_OPTION);
            $date = \DateTime::createFromFormat(DateTimeHelper::MYSQL_DATE_FORMAT, $openingSpecialHour['date'])
                ->setTimezone(wp_timezone());

            $class = $args['item_class'] ? $args['item_class'] : '';

            if ($openingSpecialHour['name']) {
                $label = sprintf('%s (%s)', $date->format($dateFormat), $openingSpecialHour['name']);
            } else {
                $label = $date->format($dateFormat);
            }

            if (!$openingSpecialHour['is_open']) {
                $value = __('Closed', I18N::DOMAIN);
            } else {
                if (!$openingSpecialHour['open_time'] && !$openingSpecialHour['close_time']) {
                    $value = __('Opened', I18N::DOMAIN);
                } else if (!$openingSpecialHour['open_time']) {
                    $value = sprintf(__('until %s', I18N::DOMAIN), substr($openingSpecialHour['close_time'], 0, 5));
                } else if (!$openingSpecialHour['close_time']) {
                    $value = sprintf(__('from %s', I18N::DOMAIN), substr($openingSpecialHour['open_time'], 0, 5));
                } else {
                    $value = sprintf(
                        '%s - %s',
                        substr($openingSpecialHour['close_time'], 0, 5),
                        substr($openingSpecialHour['open_time'], 0, 5)
                    );
                }
            }

            $result = sprintf($args['format'], esc_attr($class), esc_html($label), esc_html($value));

            if (!$args['echo']) {
                return $result;
            }

            echo $result;

            unset($date, $label, $value);
        } catch (\Exception $e) {
            if (!$args['echo']) {
                return false;
            }
        }
    }
}
