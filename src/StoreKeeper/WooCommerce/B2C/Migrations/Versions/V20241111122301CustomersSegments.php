<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentPriceModel;
use StoreKeeper\WooCommerce\B2C\Models\CustomersSegmentsModel;

class V20241111122301CustomersSegments extends AbstractMigration
{
    /**
     * @param DatabaseConnection $connection
     * @return string|null
     * @throws \Exception
     */
    public function up(DatabaseConnection $connection): ?string
    {
        $wp = CustomersSegmentsModel::getWpPrefix();
        $name = CustomersSegmentsModel::getTableName();
        $woocommerceCustomerForeignKey = CustomerSegmentPriceModel::getValidForeignFieldKey('customer_segment_id', $name);

        $query = <<<SQL
CREATE TABLE `$name` (
    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT(20) UNSIGNED NOT NULL,
    `customer_segment_id` BIGINT(20) UNSIGNED NOT NULL,
    `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
    `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE (`customer_id`, `customer_segment_id`),
    CONSTRAINT `fk_customer_id`
        FOREIGN KEY (`customer_id`)
        REFERENCES `{$wp}users` (`ID`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `{$woocommerceCustomerForeignKey}`
        FOREIGN KEY (`customer_segment_id`)
        REFERENCES `{$wp}storekeeper_customer_segments` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->querySql($query);

        return null;
    }
}
