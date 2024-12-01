<?php

namespace StoreKeeper\WooCommerce\B2C\Blocks\BlockTypes\Location;

use StoreKeeper\WooCommerce\B2C\Blocks\BlockTypes\TemplateRenderer;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\AddressModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningHourModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningSpecialHoursModel;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;

class Renderer
{

    /**
     * Render location dynamic block type
     *
     * @param array $attributes
     * @param string $content
     * @param \WP_Block $block
     * @return string
     */
    public static function render($attributes, $content, $block)
    {
        if (!array_key_exists('location', $attributes) ||
            !($location = LocationModel::get((int) $attributes['location']))) {
            return '';
        }

        if (!$location['is_active']) {
            return is_admin() ? __('The location is deactivated.', I18N::DOMAIN) : '';
        }

        $address = [];
        if (!array_key_exists('show_address', $attributes) || $attributes['show_address']) {
            $address = AddressModel::getByLocationId($location[LocationModel::PRIMARY_KEY]);
        }

        $openingHours = [];
        if (!array_key_exists('show_opening_hour', $attributes) || $attributes['show_opening_hour']) {
            $openingHours = OpeningHourModel::findBy(
                [
                    'location_id = :location_id'
                ],
                [
                    'location_id' => $location[LocationModel::PRIMARY_KEY]
                ],
                'open_day',
                'ASC'
            );
        }

        $openingSpecialHours = [];
        if (!array_key_exists('show_opening_special_hours', $attributes) || $attributes['show_opening_special_hours']) {
            $openingSpecialHours = OpeningSpecialHoursModel::findBy(
                [
                    'location_id = :location_id',
                    'date >= :date'
                ],
                [
                    'location_id' => $location[LocationModel::PRIMARY_KEY],
                    'date' => wp_date(DateTimeHelper::MYSQL_DATE_FORMAT)
                ],
                'date',
                'ASC'
            );
        }

        return TemplateRenderer::render(
            $attributes,
            $content,
            $block,
            self::class,
            [
                'location' => $location,
                'address' => $address,
                'opening_hours' => $openingHours,
                'opening_special_hours' => $openingSpecialHours
            ]
        );
    }
}
