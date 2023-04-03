<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;

class V20230312154020attribute extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        if (!AttributeModel::hasTable()) {
            $wp = AttributeModel::getWpPrefix();
            $name = AttributeModel::getTableName();

            $attributeForeignKey = AttributeModel::getValidForeignFieldKey(AttributeModel::FK_ATTRIBUTE_ID, $name);
            $query = <<<SQL
    CREATE TABLE `$name` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `attribute_id` BIGINT(20) UNSIGNED NULL UNIQUE,
        `common_name` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
        `storekeeper_id` BIGINT(20) NOT NULL UNIQUE,
        `storekeeper_alias` VARCHAR(1500) COLLATE utf8mb4_unicode_ci,
        `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
        `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
        PRIMARY KEY (`id`),
        CONSTRAINT `{$attributeForeignKey}` 
            FOREIGN KEY (`attribute_id`) 
            REFERENCES  `{$wp}woocommerce_attribute_taxonomies` (`attribute_id`)
            ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
            $connection->querySql($query);
        } else {
            return 'Table already exist';
        }

        return null;
    }
}
