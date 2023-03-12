<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

class MigrationVersionModel extends AbstractModel
{
    const TABLE_NAME = 'storekeeper_migration_versions';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'plugin_version' => true,
            'log' => false,
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
        `log` TEXT COLLATE utf8mb4_unicode_ci NULL,
        `date_created` TIMESTAMP NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        return $tableQuery;
    }

    public static function createTable(): bool
    {
        // todo, remove
    }

    public static function findNotExecutedMigrations(array $expected): array
    {
        if (empty($expected)) {
            return [];
        }
        global $wpdb;
        $select = static::getSelectHelper()
            ->cols([self::PRIMARY_KEY])
            ->orderBy([self::PRIMARY_KEY])
        ;

        $select->where(self::PRIMARY_KEY.' NOT IN :ids');
        $select->bindValues([
            'ids' => $expected,
        ]);

        $query = static::prepareQuery($select);

        return $wpdb->get_col($query);
    }
}
