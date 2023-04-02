<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\PaymentModel;

class V20230319124000PaymentsAddIsPaid extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        $name = PaymentModel::getTableName();

        $query = <<<SQL
ALTER TABLE `$name` ADD `is_paid` BOOLEAN NULL;
SQL;
        $connection->querySql($query);

        return null;
    }
}
