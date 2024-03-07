<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class ShippingZoneModel extends AbstractModel implements IModelPurge
{
    public const TABLE_NAME = 'storekeeper_shipping_zones';
    public const FK_WOOCOMMERCE_ZONE_ID = 'wc_zone_id_fk';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'wc_zone_id' => true,
            'country_iso2' => true,
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false,
        ];
    }

    public static function getByCountryIso2(string $countryIso2): ?array
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['id', 'wc_zone_id'])
            ->where('country_iso2 = :country_iso2')
            ->bindValue('country_iso2', $countryIso2);

        $query = static::prepareQuery($select);

        $results = $wpdb->get_results($query, ARRAY_A);

        if (empty($results)) {
            return null;
        }

        return reset($results);
    }

    public static function getWoocommerceZoneIds(): array
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['wc_zone_id']);

        $query = static::prepareQuery($select);

        $results = $wpdb->get_results($query, ARRAY_N);

        return array_map(
            static function ($value) {
                return (int) current($value);
            },
            $results
        );
    }

    public static function purge(): int
    {
        $delete = self::getDeleteHelper();
        global $wpdb;

        return $wpdb->query(self::prepareQuery($delete));
    }
}
