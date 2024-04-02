<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

class MigrationVersionModel extends AbstractModel
{
    public const TABLE_NAME = 'storekeeper_migration_versions';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'plugin_version' => true,
            'log' => false,
            'class' => false,
            self::FIELD_DATE_CREATED => false,
        ];
    }

    public static function getCreateSql(): string
    {
        $name = MigrationVersionModel::getTableName();
        $tableQuery = <<<SQL
    CREATE TABLE `$name` (
        `id` BIGINT(20) UNSIGNED NOT NULL,
        `plugin_version` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `log` TEXT(5000) COLLATE utf8mb4_unicode_ci NULL,
        `class` TEXT(5000) COLLATE utf8mb4_unicode_ci NULL,
        `date_created` TIMESTAMP NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        return $tableQuery;
    }

    public static function prepareInsertData(array $data): array
    {
        return self::prepareData($data, true);
    }

    public static function getAllMigrations(): array
    {
        global $wpdb;
        $select = static::getSelectHelper()->cols([self::PRIMARY_KEY]);

        $query = static::prepareQuery($select);

        return $wpdb->get_col($query);
    }
}
