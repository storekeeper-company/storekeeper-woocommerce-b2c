<?php

namespace StoreKeeper\WooCommerce\B2C\Models\Location;

use StoreKeeper\WooCommerce\B2C\Models\AbstractModel;
use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class ShippingSettingModel extends AbstractModel implements IModelPurge
{

    public const TABLE_NAME = 'storekeeper_location_shipping_setting';
    public const FK_SK_LOCATION_ID = 'sk_location_id_fk';

    public static function getFieldsWithRequired(): array
    {
        return [
            self::PRIMARY_KEY => true,
            'location_id' => true,
            'storekeeper_id' => true,
            'is_pickup' => true,
            'is_truck_delivery' => true,
            'is_pickup_next_day' => true,
            'is_truck_delivery_next_day' => true,
            'pickup_next_day_cutoff_time' => false,
            'truck_delivery_next_day_cutoff_time' => false,
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false
        ];
    }

    public static function getByLocationId($locationId, $storeKeeperId = null): ?array
    {
        global $wpdb;

        $select = static::getSelectHelper();
        $select
            ->cols(array_keys(self::getFieldsWithRequired()))
            ->where('location_id = :location_id')
            ->bindValue('location_id', (int) $locationId);

        if (is_numeric($storeKeeperId) && !is_infinite($storeKeeperId)) {
            $select
            ->where('storekeeper_id = :storekeeper_id')
            ->bindValue('storekeeper_id', (int) $storeKeeperId);
        }

        $query = static::prepareQuery($select);

        return $wpdb->get_row($query, ARRAY_A);
    }
}
