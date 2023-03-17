<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Models\AbstractModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeOptionModel;

class V2023031317165010AttributeOptionFkAttributeEnsure extends \StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        $attributeTableName = AttributeModel::getTableName();
        $tableName = AttributeOptionModel::getTableName();
        $foreignKey = AttributeOptionModel::getValidForeignFieldKey('storekeeper_attribute_id_fk', $tableName);

        if (!AbstractModel::foreignKeyExists($tableName, $foreignKey)) {
            $wpPrefix = AttributeOptionModel::getWpPrefix();
            $connection->querySql(
                <<<SQL
DELETE FROM `$tableName` WHERE id IN (
    SELECT id FROM `$tableName` o WHERE NOT EXISTS (
        SELECT 1 FROM `$attributeTableName` t WHERE t.attribute_id = o.attribute_id
    )
); -- remove not existsing ids
SQL
            );
            $connection->querySql(
                <<<SQL
ALTER TABLE `$tableName` 
    ADD CONSTRAINT `$foreignKey` 
        FOREIGN KEY (`storekeeper_attribute_id`) 
        REFERENCES `{$attributeTableName}` (`id`) 
        ON DELETE CASCADE ON UPDATE CASCADE;
SQL
            );
        } else {
            return 'Key '.$foreignKey.' already exists on table '.$tableName;
        }
    }
}
