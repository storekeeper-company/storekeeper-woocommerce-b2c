<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class PaymentModel extends AbstractModel implements IModelPurge
{
    const TABLE_NAME = 'storekeeper_pay_orders_payments';

    const PRIMARY_KEY = 'order_id';

    public static function getFieldsWithRequired(): array
    {
        return [
            self::PRIMARY_KEY => true,
            'payment_id' => true,
            'amount' => false,
            'is_synced' => true,
        ];
    }

    public static function prepareInsertData(array $data): array
    {
        return self::prepareData($data, true);
    }
}
