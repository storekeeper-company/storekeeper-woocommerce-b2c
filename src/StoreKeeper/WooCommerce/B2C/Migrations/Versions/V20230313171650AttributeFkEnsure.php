<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\AbstractModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;

class V20230313171650AttributeFkEnsure extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        $tableName = AttributeModel::getTableName();
        $foreignKey = AttributeModel::getValidForeignFieldKey(AttributeModel::FK_ATTRIBUTE_ID, $tableName);

        if (!AbstractModel::foreignKeyExists($tableName, $foreignKey)) {
            $wp = AttributeModel::getWpPrefix();
            $connection->querySql(
                <<<SQL
DELETE FROM `$tableName` WHERE id IN (
    SELECT id FROM `$tableName` o WHERE NOT EXISTS (
        SELECT 1 FROM `{$wp}woocommerce_attribute_taxonomies` t WHERE t.attribute_id = o.attribute_id
    )
); -- remove not existsing ids
SQL
            );
            $connection->querySql(
                <<<SQL
ALTER TABLE `$tableName` 
    ADD CONSTRAINT `$foreignKey` 
        FOREIGN KEY (`attribute_id`) 
        REFERENCES `{$wp}woocommerce_attribute_taxonomies` (`attribute_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE;
SQL
            );
        } else {
            return 'Key '.$foreignKey.' already exists on table '.$tableName;
        }

        return null;
    }
}
