<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentModel;

class V20241105122301CustomerSegments extends AbstractMigration
{
    /**
     * @param DatabaseConnection $connection
     * @return string|null
     * @throws \Exception
     */
    public function up(DatabaseConnection $connection): ?string
    {
        $name = CustomerSegmentModel::getTableName();
        $query = <<<SQL
CREATE TABLE `$name` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
    PRIMARY KEY (`id`),
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->querySql($query);

        return null;
    }
}
