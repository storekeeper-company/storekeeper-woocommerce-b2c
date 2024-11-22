<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class CustomersInSegmentsModel extends AbstractModel implements IModelPurge
{
    public const TABLE_NAME = 'storekeeper_customer_in_segments';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'customer_id' => false,
            'customer_segment_id' => true,
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false,
        ];
    }
}
