<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class CustomerSegmentPriceModel extends AbstractModel implements IModelPurge
{
    public const TABLE_NAME = 'storekeeper_customer_segment_prices';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'customer_segment_id' => false,
            'product_id' => true,
            'from_qty' => true,
            'ppu_wt' => true,
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false,
        ];
    }
}
