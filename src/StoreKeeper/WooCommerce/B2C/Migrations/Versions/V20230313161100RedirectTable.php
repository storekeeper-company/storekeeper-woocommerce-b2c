<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Tools\RedirectHandler;

class V20230313161100RedirectTable extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        if (!RedirectHandler::createTable()) {
            return 'Table already exist';
        }

        return null;
    }
}
