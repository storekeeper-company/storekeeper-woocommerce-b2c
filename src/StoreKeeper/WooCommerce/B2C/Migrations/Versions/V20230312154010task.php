<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;

class V20230312154010task extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        if (!TaskModel::hasTable()) {
            $name = TaskModel::getTableName();

            $query = <<<SQL
    CREATE TABLE `$name` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `status` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `times_ran` INT(10) DEFAULT 0,
        `name` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `type` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `type_group` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `storekeeper_id` INT(10) COLLATE utf8mb4_unicode_ci NOT NULL,
        `meta_data` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `execution_duration` TEXT COLLATE utf8mb4_unicode_ci,
        `date_last_processed` TIMESTAMP NULL,
        `error_output` LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
        `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
        `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
        PRIMARY KEY (`id`),
        INDEX (type_group(100))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
            $connection->querySql($query);
        } else {
            return 'Table already exist';
        }

        return null;
    }
}
