<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;

class V20230312154000webhook extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        if (!WebhookLogModel::hasTable()) {
            $name = WebhookLogModel::getTableName();

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

            $connection->querySql($query);
        } else {
            return 'Table already exist';
        }

        return null;
    }
}
