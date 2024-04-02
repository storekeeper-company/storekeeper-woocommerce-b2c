<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class WebhookLogModel extends AbstractModel implements IModelPurge
{
    public const TABLE_NAME = 'storekeeper_webhook_logs';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'action' => true,
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false,
            'body' => true,
            'headers' => true,
            'method' => true,
            'response_code' => true,
            'route' => true,
        ];
    }

    public static function purge(): int
    {
        $affectedRows = 0;

        $affectedRows += static::purgeOrderThanXDays(30);

        if (static::count() > 1000) {
            $affectedRows += static::purgeOrderThanXDays(7);
            $affectedRows += static::purgeAllKeepLast1000();
        }

        return $affectedRows;
    }

    private static function purgeAllKeepLast1000()
    {
        global $wpdb;

        $name = static::getTableName();
        $getQuery = <<<SQL
    SELECT id
    FROM $name
    ORDER BY id DESC 
    LIMIT 1
    OFFSET 999
SQL;

        $id = $wpdb->get_var($getQuery);
        $delete = static::getDeleteHelper()
            ->where('id < :id')
            ->bindValue('id', $id);

        $deleteQuery = static::prepareQuery($delete);

        $affectedRows = $wpdb->query($deleteQuery);

        AbstractModel::ensureAffectedRows($affectedRows);

        return $affectedRows;
    }

    private static function purgeOrderThanXDays(int $numberOfDays): int
    {
        global $wpdb;

        $numberOfDays = absint($numberOfDays);

        $delete = static::getDeleteHelper()
            ->where('date_created < :old')
            ->bindValue('old', DatabaseConnection::formatToDatabaseDate(
                (new \DateTime())->setTimestamp(strtotime("-$numberOfDays days"))
            ));

        $affectedRows = $wpdb->query(static::prepareQuery($delete));

        AbstractModel::ensureAffectedRows($affectedRows);

        return (int) $affectedRows;
    }
}
