<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

/**
 * @since 8.1.0
 */
class RefundModel extends AbstractModel implements IModelPurge
{
    public const TABLE_NAME = 'storekeeper_pay_orders_refunds';

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
}
