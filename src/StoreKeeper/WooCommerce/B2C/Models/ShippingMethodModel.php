<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class ShippingMethodModel extends AbstractModel implements IModelPurge
{
    public const TABLE_NAME = 'storekeeper_shipping_methods';
    public const FK_WOOCOMMERCE_INSTANCE_ID = 'wc_instance_id_fk';
    public const FK_STOREKEEPER_ZONE_ID = 'sk_zone_id_fk';

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

    public static function getInstanceIdByShippingZoneAndStoreKeeperId(int $storeKeeperZoneId, int $storeKeeperId): ?int
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

        $result = current($results);

        return (int) current($result);
    }

    public static function getShippingZoneIdsByStoreKeeperId(int $storeKeeperId): ?array
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['sk_zone_id'])
            ->where('storekeeper_id = :storekeeper_id')
            ->bindValue('storekeeper_id', $storeKeeperId);

        $query = static::prepareQuery($select);
        $results = $wpdb->get_results($query, ARRAY_N);

        if (empty($results)) {
            return null;
        }

        return array_map(
            static function ($value) {
                return (int) current($value);
            },
            $results
        );
    }

    public static function getUniqueStoreKeeperIds(array $whereClauses = [], array $whereValues = []): ?array
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['storekeeper_id'])
            ->groupBy(['storekeeper_id'])
            ->orderBy(['storekeeper_id ASC']);

        if (!empty($whereValues)) {
            $select->bindValues($whereValues);
        }

        foreach ($whereClauses as $whereClause) {
            $select->where($whereClause);
        }

        $query = static::prepareQuery($select);
        $results = $wpdb->get_results($query, ARRAY_N);

        if (empty($results)) {
            return null;
        }

        return array_map(
            static function ($value) {
                return (int) current($value);
            },
            $results
        );
    }

    public static function methodExists(int $storekeeperId)
    {
        return self::count(['storekeeper_id = :storekeeper_id'], ['storekeeper_id' => $storekeeperId]) > 0;
    }

    public static function purge(): int
    {
        $delete = self::getDeleteHelper();
        global $wpdb;

        return $wpdb->query(self::prepareQuery($delete));
    }
}
