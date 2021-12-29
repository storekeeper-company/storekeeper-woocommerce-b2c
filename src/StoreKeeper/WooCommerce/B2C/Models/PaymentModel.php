<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class PaymentModel extends AbstractModel implements IModelPurge
{
    const TABLE_NAME = 'storekeeper_pay_orders_payments';
    const TABLE_VERSION = '1.0.0';

    public static function getFieldsWithRequired(): array
    {
        return [
            'order_id' => true,
            'payment_id' => true,
            'is_synced' => true,
        ];
    }

    public static function createTable(): bool
    {
        $name = self::getTableName();

        $tableQuery = <<<SQL
    CREATE TABLE `$name` (
        `order_id` bigint(20) NOT NULL,
        `payment_id` bigint(20) NOT NULL,
        `is_synced` boolean NOT NULL DEFAULT 0,
        PRIMARY KEY (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        return static::querySql($tableQuery);
    }
}
