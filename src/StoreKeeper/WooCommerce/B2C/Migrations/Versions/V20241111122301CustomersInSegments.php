<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\CustomersInSegmentsModel;

class V20241111122301CustomersInSegments extends AbstractMigration
{
    /**
     * @param DatabaseConnection $connection
     * @return string|null
     * @throws \Exception
     */
    public function up(DatabaseConnection $connection): ?string
    {
        $wp = CustomersInSegmentsModel::getWpPrefix();
        $name = CustomersInSegmentsModel::getTableName();

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
    CONSTRAINT `customer_segment_id`
        FOREIGN KEY (`customer_segment_id`)
        REFERENCES `{$wp}storekeeper_customer_segments` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $connection->querySql($query);

        return null;
    }
}
