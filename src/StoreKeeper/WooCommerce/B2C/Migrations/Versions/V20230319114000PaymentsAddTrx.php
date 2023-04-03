<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Models\PaymentModel;

class V20230319114000PaymentsAddTrx extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        $name = PaymentModel::getTableName();

        $query = <<<SQL
ALTER TABLE `$name` ADD `trx` TEXT(40) NULL, ADD INDEX `{$name}_trx` (`trx`(40));
SQL;
        $connection->querySql($query);

        return null;
    }
}
