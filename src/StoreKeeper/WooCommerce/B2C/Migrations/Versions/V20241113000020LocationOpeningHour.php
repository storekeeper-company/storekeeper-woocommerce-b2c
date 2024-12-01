<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningHourModel;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;

class V20241113000020LocationOpeningHour extends AbstractMigration
{

    public function up(DatabaseConnection $connection): ?string
    {
        $name = OpeningHourModel::getTableName();

        if (OpeningHourModel::hasTable()) {
            return 'Table `' . $name . '` already exist';
        }

        $idFieldName = OpeningHourModel::PRIMARY_KEY;
        $name = OpeningHourModel::getTableName();
        $locationForeignKey = OpeningHourModel::getValidForeignFieldKey(OpeningHourModel::FK_SK_LOCATION_ID, $name);
        $locationTable = LocationModel::getTableName();
        $locationIdFieldName = LocationModel::PRIMARY_KEY;

        $query = <<<SQL
CREATE TABLE `{$name}` (
    `{$idFieldName}` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `location_id` INT(10) UNSIGNED NOT NULL,
    `storekeeper_id` INT(10) NOT NULL,
    `open_day` TINYINT(1) UNSIGNED NOT NULL,
    `open_time` TIME NOT NULL,
    `close_time` TIME NOT NULL,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
    PRIMARY KEY (`{$idFieldName}`),
    INDEX `{$name}_storekeeper_id` (`storekeeper_id`),
    CONSTRAINT `{$name}_sk_id_open_day_uq` UNIQUE (`storekeeper_id`, `open_day`),
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
