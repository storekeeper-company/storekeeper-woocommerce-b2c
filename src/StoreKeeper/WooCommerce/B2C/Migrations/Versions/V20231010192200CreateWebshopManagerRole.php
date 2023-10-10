<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Helpers\RoleHelper;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;

class V20231010192200CreateWebshopManagerRole extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        RoleHelper::recreateRoles();

        return null;
    }
}
