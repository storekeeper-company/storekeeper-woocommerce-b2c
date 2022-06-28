<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class PaymentModel extends AbstractModel implements IModelPurge
{
    const TABLE_NAME = 'storekeeper_pay_orders_payments';
    const TABLE_VERSION = '1.1.0';

    public static function getFieldsWithRequired(): array
    {
        return [
            'order_id' => true,
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
        `order_id` bigint(20) NOT NULL,
        `payment_id` bigint(20) NOT NULL,
        `amount` TEXT COLLATE utf8mb4_unicode_ci NULL,
        `is_synced` boolean NOT NULL DEFAULT 0,
        PRIMARY KEY (`order_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        return static::querySql($tableQuery);
    }

    public static function alterTable(): void
    {
        global $wpdb;

        $fields = array_keys(static::getFieldsWithRequired());
        $tableColumns = $wpdb->get_results("SELECT column_name FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = '".static::getTableName()."'", ARRAY_A);
        $tableColumns = array_column($tableColumns, 'column_name');

        $difference = array_diff($fields, $tableColumns);

        /* @since 8.1.0 */
        if (in_array('amount', $difference, true)) {
            $wpdb->query('ALTER TABLE '.static::getTableName().' ADD amount TEXT COLLATE utf8mb4_unicode_ci NULL');
        }
    }
}
