<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\AbstractModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeOptionModel;

class V20230313171651AttributeOptionFkAttributeEnsure extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        $tableName = AttributeOptionModel::getTableName();
        $foreignKey = AttributeOptionModel::getValidForeignFieldKey(AttributeOptionModel::FK_STOREKEEPER_ATTRIBUTE_ID, $tableName);
        $attributeTableName = AttributeModel::getTableName();

        if (!AbstractModel::foreignKeyExists($tableName, $foreignKey)) {
            $connection->querySql(
                <<<SQL
DELETE FROM `$tableName` WHERE id IN (
    SELECT id FROM `$tableName` o WHERE NOT EXISTS (
        SELECT 1 FROM `$attributeTableName` t WHERE t.id = o.storekeeper_attribute_id
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

        return null;
    }
}
