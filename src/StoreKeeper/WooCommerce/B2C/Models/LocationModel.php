<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class LocationModel extends AbstractModel implements IModelPurge
{

    public const TABLE_NAME = 'storekeeper_location';

    public static function getFieldsWithRequired(): array
    {
        return [
            self::PRIMARY_KEY => true,
            'storekeeper_id' => true,
            'name' => true,
            'is_default' => false,
            'is_active' => false,
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false
        ];
    }

    public static function getByStoreKeeperId($storekeeperId): ?array
    {
        $items = self::findBy(
            ['storekeeper_id = :storekeeper_id'],
            ['storekeeper_id' => (int) $storekeeperId],
            null,
            null,
            1
        );

        if ($items) {
            $item = reset($items);

            try {
                static::validateData($item, true);
            } catch (\Exception $exception) {
                $name = static::getTableName();
                throw new \Exception("Got invalid data from the \"$name\" database.", null, $exception);
            }

            return $item;
        }

        return null;
    }

    public static function getStoreKeeperId($locationId): ?int
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['storekeeper_id'])
            ->where(self::PRIMARY_KEY . ' = :location_id')
            ->bindValue('location_id', (int) $locationId);

        $query = static::prepareQuery($select);

        $storekeeperId = $wpdb->get_var($query);

        if (is_numeric($storekeeperId)) {
            return (int) $storekeeperId;
        }

        return null;
    }

    public static function getLocationIdByStoreKeeperId($storekeeperId): ?int
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols([self::PRIMARY_KEY])
            ->where('storekeeper_id = :storekeeper_id')
            ->bindValue('storekeeper_id', (int) $storekeeperId);

        $query = static::prepareQuery($select);

        $locationId = $wpdb->get_var($query);

        if (is_numeric($locationId)) {
            return (int) $locationId;
        }

        return null;
    }
}
