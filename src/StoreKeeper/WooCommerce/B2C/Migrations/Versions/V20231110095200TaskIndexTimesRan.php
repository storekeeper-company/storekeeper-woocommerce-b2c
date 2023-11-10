<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;

class V20231110095200TaskIndexTimesRan extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        $name = TaskModel::getTableName();

        $query = <<<SQL
ALTER TABLE `$name` ADD INDEX `{$name}_times_ran` (`times_ran`);
SQL;
        $connection->querySql($query);

        return null;
    }
}
