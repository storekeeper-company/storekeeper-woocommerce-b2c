<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;

abstract class AbstractMigration
{
    abstract public function up(DatabaseConnection $connection): ?string;
}
