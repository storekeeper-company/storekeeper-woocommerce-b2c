<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;

class V20241113000000Location extends AbstractMigration
{

    public function up(DatabaseConnection $connection): ?string
    {
        $name = LocationModel::getTableName();

        if (LocationModel::hasTable()) {
            return 'Table `' . $name . '` already exist';
        }

        $idFieldName = LocationModel::PRIMARY_KEY;

        $query = <<<SQL
CREATE TABLE `{$name}` (
    `{$idFieldName}` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `storekeeper_id` INT(10) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `is_default` BOOLEAN NOT NULL DEFAULT 0,
    `is_active` BOOLEAN NOT NULL DEFAULT 1,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
    PRIMARY KEY (`{$idFieldName}`),
    UNIQUE (`storekeeper_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->querySql($query);

        return null;
    }
}
