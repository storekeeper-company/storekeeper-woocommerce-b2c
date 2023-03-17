<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Models\AbstractModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;

class V2023031317165000AttributeFkEnsure extends \StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        $tableName = AttributeModel::getTableName();
        $foreignKey = AttributeModel::getValidForeignFieldKey('attribute_id_fk', $tableName);

        if (!AbstractModel::foreignKeyExists($tableName, $foreignKey)) {
            $wpPrefix = AttributeModel::getWpPrefix();
            $connection->querySql(
                <<<SQL
DELETE FROM `$tableName` WHERE id IN (
    SELECT id FROM `$tableName` o WHERE NOT EXISTS (
        SELECT 1 FROM `{$wpPrefix}woocommerce_attribute_taxonomies` t WHERE t.attribute_id = o.attribute_id
    )
); -- remove not existsing ids
SQL
            );
            $connection->querySql(
                <<<SQL
ALTER TABLE `$tableName` 
    ADD CONSTRAINT `$foreignKey` 
        FOREIGN KEY (`attribute_id`) 
        REFERENCES `{$wpPrefix}woocommerce_attribute_taxonomies` (`attribute_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE;
SQL
            );
        } else {
            return 'Key '.$foreignKey.' already exists on table '.$tableName;
        }
    }
}
