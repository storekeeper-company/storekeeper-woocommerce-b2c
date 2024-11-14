<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentPriceModel;

class V20241105122301CustomerSegmentPrices extends AbstractMigration
{
    /**
     * @throws \Exception
     */
    public function up(DatabaseConnection $connection): ?string
    {
        $wp = CustomerSegmentPriceModel::getWpPrefix();
        $name = CustomerSegmentPriceModel::getTableName();

        $query = <<<SQL
        CREATE TABLE `$name` (
            `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `customer_segment_id` BIGINT(20) UNSIGNED NOT NULL,
            `product_id` BIGINT(20) UNSIGNED NOT NULL,
            `from_qty` INT(11) UNSIGNED NOT NULL,
            `ppu_wt` DECIMAL(10, 2) NOT NULL,
            `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
            `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk_customer_segment`
                FOREIGN KEY (`customer_segment_id`) 
                REFERENCES `{$wp}storekeeper_customer_segments` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_product`
                FOREIGN KEY (`product_id`) 
                REFERENCES `{$wp}posts` (`ID`)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL;
        $connection->querySql($query);

        return null;
    }
}
