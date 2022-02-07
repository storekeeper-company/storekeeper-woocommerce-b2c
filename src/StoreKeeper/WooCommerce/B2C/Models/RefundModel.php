<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

/**
 * @since 8.1.0
 */
class RefundModel extends AbstractModel implements IModelPurge
{
    const TABLE_NAME = 'storekeeper_pay_orders_refunds';
    const TABLE_VERSION = '1.0.0';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'wc_order_id' => true,
            'wc_refund_id' => true,
            'sk_refund_id' => false,
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
        `wc_order_id` bigint(20) NOT NULL,
        `wc_refund_id` bigint(20) NOT NULL,
        `sk_refund_id` bigint(20) NULL,
        `amount` TEXT COLLATE utf8mb4_unicode_ci NULL,
        `is_synced` boolean NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        return static::querySql($tableQuery);
    }
}
