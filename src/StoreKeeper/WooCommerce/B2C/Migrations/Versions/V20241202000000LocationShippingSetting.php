<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\Location\ShippingSettingModel;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;

class V20241202000000LocationShippingSetting extends AbstractMigration
{

    public function up(DatabaseConnection $connection): ?string
    {
        $name = ShippingSettingModel::getTableName();

        if (ShippingSettingModel::hasTable()) {
            return 'Table `' . $name . '` already exist';
        }

        $idFieldName = ShippingSettingModel::PRIMARY_KEY;
        $name = ShippingSettingModel::getTableName();
        $locationForeignKey = ShippingSettingModel::getValidForeignFieldKey(
            ShippingSettingModel::FK_SK_LOCATION_ID,
            $name
        );
        $locationTable = LocationModel::getTableName();
        $locationIdFieldName = LocationModel::PRIMARY_KEY;

        $query = <<<SQL
CREATE TABLE `{$name}` (
    `{$idFieldName}` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `location_id` INT(10) UNSIGNED NOT NULL,
    `storekeeper_id` INT(10) NOT NULL,
    `is_pickup` BOOLEAN NOT NULL DEFAULT 0,
    `is_truck_delivery` BOOLEAN NOT NULL DEFAULT 0,
    `is_pickup_next_day` BOOLEAN NOT NULL DEFAULT 0,
    `is_truck_delivery_next_day` BOOLEAN NOT NULL DEFAULT 0,
    `pickup_next_day_cutoff_time` TIME DEFAULT NULL,
    `truck_delivery_next_day_cutoff_time` TIME DEFAULT NULL,
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
