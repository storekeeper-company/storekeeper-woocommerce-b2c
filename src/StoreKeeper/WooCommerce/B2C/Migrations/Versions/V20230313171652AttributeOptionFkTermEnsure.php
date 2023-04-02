<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\AbstractModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeOptionModel;

class V20230313171652AttributeOptionFkTermEnsure extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        $tableName = AttributeOptionModel::getTableName();
        $foreignKey = AttributeOptionModel::getValidForeignFieldKey(AttributeOptionModel::FK_TERM_ID, $tableName);

        if (!AbstractModel::foreignKeyExists($tableName, $foreignKey)) {
            $wp = AttributeOptionModel::getWpPrefix();
            $connection->querySql(
                <<<SQL
DELETE FROM `$tableName` WHERE id IN (
    SELECT id FROM `$tableName` o WHERE NOT EXISTS (
        SELECT 1 FROM `{$wp}terms` t WHERE t.term_id = o.term_id
    )
); -- remove not existsing ids
SQL
            );
            $connection->querySql(
                <<<SQL
ALTER TABLE `$tableName` 
    ADD CONSTRAINT `$foreignKey` 
        FOREIGN KEY (`term_id`) 
        REFERENCES `{$wp}terms` (`term_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE;
SQL
            );
        } else {
            return 'Key '.$foreignKey.' already exists on table '.$tableName;
        }

        return null;
    }
}
