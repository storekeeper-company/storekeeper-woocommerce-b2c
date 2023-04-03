<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\PaymentModel;

class V20230312154040Payment extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        if (!PaymentModel::hasTable()) {
            $name = PaymentModel::getTableName();

            $query = <<<SQL
    CREATE TABLE `$name` (
        `order_id` bigint(20) NOT NULL,
        `payment_id` bigint(20) NOT NULL,
        `amount` TEXT COLLATE utf8mb4_unicode_ci NULL,
        `is_synced` boolean NOT NULL DEFAULT 0,
        PRIMARY KEY (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
            $connection->querySql($query);
        } else {
            return 'Table already exist';
        }

        return null;
    }
}
