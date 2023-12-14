<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\ShippingZoneModel;

class V20231126172100ShippingZones extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        $wp = ShippingZoneModel::getWpPrefix();
        $name = ShippingZoneModel::getTableName();
        $woocommerceZoneForeignKey = ShippingZoneModel::getValidForeignFieldKey(ShippingZoneModel::FK_WOOCOMMERCE_ZONE_ID, $name);

        $query = <<<SQL
CREATE TABLE `$name` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `wc_zone_id` BIGINT(20) UNSIGNED NULL UNIQUE,
    `country_iso2` VARCHAR(4) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `{$woocommerceZoneForeignKey}` 
        FOREIGN KEY (`wc_zone_id`) 
        REFERENCES  `{$wp}woocommerce_shipping_zones` (`zone_id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->querySql($query);

        return null;
    }
}
