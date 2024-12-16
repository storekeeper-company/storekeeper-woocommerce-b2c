<?php

namespace StoreKeeper\WooCommerce\B2C\Models\Location;

use StoreKeeper\WooCommerce\B2C\Models\AbstractModel;
use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class OpeningSpecialHoursModel extends AbstractModel implements IModelPurge
{

    public const TABLE_NAME = 'storekeeper_location_opening_special_hours';
    public const FK_SK_LOCATION_ID = 'sk_location_id_fk';

    public static function getFieldsWithRequired(): array
    {
        return [
            self::PRIMARY_KEY => true,
            'location_id' => true,
            'storekeeper_id' => true,
            'name' => false,
            'date' => true,
            'is_open' => true,
            'open_time' => false,
            'close_time' => false,
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false
        ];
    }

    public static function getByLocationId($locationId): array
    {
        global $wpdb;

        $select = static::getSelectHelper();
        $select
            ->cols(array_keys(self::getFieldsWithRequired()))
            ->where('location_id = :location_id')
            ->bindValue('location_id', (int) $locationId)
            ->orderBy(['date ASC']);

        $query = static::prepareQuery($select);

        return $wpdb->get_results($query, ARRAY_A);
    }
}
