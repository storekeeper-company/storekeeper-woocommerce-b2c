<?php

namespace StoreKeeper\WooCommerce\B2C\Models\Location;

use StoreKeeper\WooCommerce\B2C\Models\AbstractModel;
use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class AddressModel extends AbstractModel implements IModelPurge
{

    public const TABLE_NAME = 'storekeeper_location_address';
    public const FK_SK_LOCATION_ID = 'sk_location_id_fk';

    public static function getFieldsWithRequired(): array
    {
        return [
            self::PRIMARY_KEY => true,
            'location_id' => true,
            'storekeeper_id' => true,
            'city' => false,
            'zipcode' => false,
            'state' => false,
            'phone' => false,
            'email' => false,
            'url' => false,
            'street' => false,
            'streetnumber' => false,
            'flatnumber' => false,
            'country' => false,
            'published' => false,
            'gln' => false,
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false
        ];
    }

    public static function getByLocationId($locationId): ?array
    {
        global $wpdb;

        $select = static::getSelectHelper();
        $select
            ->cols(array_keys(self::getFieldsWithRequired()))
            ->where('location_id = :location_id')
            ->limit(1)
            ->bindValue('location_id', (int) $locationId);

        $query = static::prepareQuery($select);

        return $wpdb->get_row($query, ARRAY_A);
    }
}
