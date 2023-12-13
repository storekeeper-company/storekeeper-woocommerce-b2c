<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\ShippingMethodModel;

class V20231204152300ShippingMethods extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        $wp = ShippingMethodModel::getWpPrefix();
        $name = ShippingMethodModel::getTableName();
        $woocommerceInstanceForeignKey = ShippingMethodModel::getValidForeignFieldKey(ShippingMethodModel::FK_WOOCOMMERCE_INSTANCE_ID, $name);
        $storekeeperZoneForeignKey = ShippingMethodModel::getValidForeignFieldKey(ShippingMethodModel::FK_STOREKEEPER_ZONE_ID, $name);

        $query = <<<SQL
CREATE TABLE `$name` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `wc_instance_id` BIGINT(20) UNSIGNED NULL UNIQUE,
    `storekeeper_id` INT(10) COLLATE utf8mb4_unicode_ci NOT NULL,
    `sk_zone_id` BIGINT(20) UNSIGNED NULL,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `{$woocommerceInstanceForeignKey}`
        FOREIGN KEY (`wc_instance_id`)
        REFERENCES  `{$wp}woocommerce_shipping_zone_methods` (`instance_id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `{$storekeeperZoneForeignKey}`
        FOREIGN KEY (`sk_zone_id`)
        REFERENCES  `{$wp}storekeeper_shipping_zones` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->querySql($query);

        return null;
    }
}
