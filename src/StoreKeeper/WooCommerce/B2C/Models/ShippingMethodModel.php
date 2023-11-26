<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class ShippingMethodModel extends AbstractModel implements IModelPurge
{
    const TABLE_NAME = 'storekeeper_shipping_methods';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'wc_instance_id' => true,
            'storekeeper_id' => true,
            'sk_zone_id' => true, // ShippingZoneModel::id
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false,
        ];
    }

    public static function getInstanceIdByStorekeeperZoneAndId(int $storeKeeperZoneId, int $storeKeeperId): ?int
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['wc_instance_id'])
            ->where('storekeeper_id = :storekeeper_id')
            ->where('sk_zone_id = :sk_zone_id')
            ->bindValue('storekeeper_id', $storeKeeperId)
            ->bindValue('sk_zone_id', $storeKeeperZoneId);

        $query = static::prepareQuery($select);
        $results = $wpdb->get_results($query, ARRAY_N);

        if (empty($results)) {
            return null;
        }

        return (int) reset($results);
    }
}
