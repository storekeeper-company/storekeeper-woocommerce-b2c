<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class WebhookLogModel extends AbstractModel implements IModelPurge
{
    const TABLE_NAME = 'storekeeper_webhook_logs';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'action' => true,
            'date_created' => false,
            'date_updated' => false,
            'body' => true,
            'headers' => true,
            'method' => true,
            'response_code' => true,
            'route' => true,
        ];
    }

    public static function createTable(): bool
    {
        $name = static::getTableName();

        $query = <<<SQL
    CREATE TABLE `$name` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `action` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `body` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `headers` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `method` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `response_code` INT(3) NOT NULL,
        `route` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
        `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        return static::querySql($query);
    }

    public static function update($id, array $data): void
    {
        $data = parent::updateDateField($data);
        parent::update($id, $data);
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

    public static function alterTable(): void
    {
        // No implementation yet.
    }
}
