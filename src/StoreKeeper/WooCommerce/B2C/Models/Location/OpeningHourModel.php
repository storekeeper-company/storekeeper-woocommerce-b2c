<?php

namespace StoreKeeper\WooCommerce\B2C\Models\Location;

use StoreKeeper\WooCommerce\B2C\Models\AbstractModel;
use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class OpeningHourModel extends AbstractModel implements IModelPurge
{

    public const TABLE_NAME = 'storekeeper_location_opening_hour';
    public const FK_SK_LOCATION_ID = 'sk_location_id_fk';

    public static function getFieldsWithRequired(): array
    {
        return [
            self::PRIMARY_KEY => true,
            'location_id' => true,
            'storekeeper_id' => true,
            'open_day' => true,
            'open_time' => true,
            'close_time' => true,
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
            ->bindValue('location_id', (int) $locationId);

        $query = static::prepareQuery($select);

        return $wpdb->get_results($query, ARRAY_A);
    }
}
