<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningSpecialHoursModel;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;

class V20241113000030LocationOpeningSpecialHours extends AbstractMigration
{

    public function up(DatabaseConnection $connection): ?string
    {
        $name = OpeningSpecialHoursModel::getTableName();

        if (OpeningSpecialHoursModel::hasTable()) {
            return 'Table `' . $name . '` already exist';
        }

        $idFieldName = OpeningSpecialHoursModel::PRIMARY_KEY;
        $name = OpeningSpecialHoursModel::getTableName();
        $locationForeignKey = OpeningSpecialHoursModel::getValidForeignFieldKey(
            OpeningSpecialHoursModel::FK_SK_LOCATION_ID,
            $name
        );
        $locationTable = LocationModel::getTableName();
        $locationIdFieldName = LocationModel::PRIMARY_KEY;

        $query = <<<SQL
CREATE TABLE `{$name}` (
    `{$idFieldName}` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `location_id` INT(10) UNSIGNED NOT NULL,
    `storekeeper_id` INT(10) NOT NULL,
    `name` VARCHAR(255) DEFAULT NULL,
    `date` DATE NOT NULL,
    `is_open` BOOLEAN NOT NULL DEFAULT 1,
    `open_time` TIME DEFAULT NULL,
    `close_time` TIME DEFAULT NULL,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
    PRIMARY KEY (`{$idFieldName}`),
    UNIQUE (`storekeeper_id`),
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
