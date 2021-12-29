<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

/**
 * @since 7.6.10
 */
class RefundModel extends AbstractModel implements IModelPurge
{
    const TABLE_NAME = 'storekeeper_pay_orders_refunds';
    const TABLE_VERSION = '1.0.0';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'order_id' => true,
            'refund_id' => true,
            'payment_id' => true,
            'amount' => false,
            'is_synced' => true,
        ];
    }

    public static function createTable(): bool
    {
        $name = self::getTableName();

        $tableQuery = <<<SQL
    CREATE TABLE `$name` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `order_id` bigint(20) NOT NULL,
        `refund_id` bigint(20) NOT NULL,
        `payment_id` bigint(20) NOT NULL,
        `amount` TEXT COLLATE utf8mb4_unicode_ci NULL,
        `is_synced` boolean NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        return static::querySql($tableQuery);
    }
}
