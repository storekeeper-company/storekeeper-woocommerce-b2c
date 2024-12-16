<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\Location\AddressModel;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;

class V20241113000010LocationAddress extends AbstractMigration
{

    public function up(DatabaseConnection $connection): ?string
    {
        $name = AddressModel::getTableName();

        if (AddressModel::hasTable()) {
            return 'Table `' . $name . '` already exist';
        }

        $idFieldName = AddressModel::PRIMARY_KEY;
        $name = AddressModel::getTableName();
        $locationForeignKey = AddressModel::getValidForeignFieldKey(AddressModel::FK_SK_LOCATION_ID, $name);
        $locationTable = LocationModel::getTableName();
        $locationIdFieldName = LocationModel::PRIMARY_KEY;

        $query = <<<SQL
CREATE TABLE `{$name}` (
    `{$idFieldName}` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `location_id` INT(10) UNSIGNED NOT NULL,
    `storekeeper_id` INT(10) NOT NULL,
    `city` VARCHAR(255) DEFAULT NULL,
    `zipcode` VARCHAR(255) DEFAULT NULL,
    `state` VARCHAR(255) DEFAULT NULL,
    `phone` VARCHAR(255) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `url` VARCHAR(255) DEFAULT NULL,
    `street` VARCHAR(255) DEFAULT NULL,
    `streetnumber` VARCHAR(255) DEFAULT NULL,
    `flatnumber` VARCHAR(255) DEFAULT NULL,
    `country` VARCHAR(2) DEFAULT NULL,
    `gln` BIGINT(14) UNSIGNED DEFAULT NULL,
    `published` BOOLEAN NOT NULL DEFAULT 0,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
    PRIMARY KEY (`{$idFieldName}`),
    UNIQUE (`storekeeper_id`),
    UNIQUE (`location_id`),
    CONSTRAINT `{$locationForeignKey}`
        FOREIGN KEY (`location_id`)
        REFERENCES  `{$locationTable}` (`{$locationIdFieldName}`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $connection->querySql($query);

        return null;
    }
}
