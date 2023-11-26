<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\ShippingMethodModel;
use StoreKeeper\WooCommerce\B2C\Models\ShippingZoneModel;

class V20231126172100ShippingZonesAndMethods extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        if (!ShippingZoneModel::hasTable()) {
            $name = ShippingZoneModel::getTableName();

            $query = <<<SQL
    CREATE TABLE `$name` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `wc_zone_id` bigint(20) NOT NULL,
        `country_iso2` VARCHAR(4) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
        `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
        `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
            $connection->querySql($query);
        } else {
            return 'Table already exist';
        }

        if (!ShippingMethodModel::hasTable()) {
            $name = ShippingMethodModel::getTableName();

            $query = <<<SQL
    CREATE TABLE `$name` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `wc_instance_id` bigint(20) NOT NULL,
        `storekeeper_id` INT(10) COLLATE utf8mb4_unicode_ci NOT NULL,
        `sk_zone_id` INT(10) COLLATE utf8mb4_unicode_ci NOT NULL,
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
