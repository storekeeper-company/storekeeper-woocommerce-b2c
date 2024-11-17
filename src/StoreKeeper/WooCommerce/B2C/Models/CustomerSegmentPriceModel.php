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

    public static function findByCustomerSegmentId($productId, $customerSegmentId, $qty)
    {
        global $wpdb;
        $segmentPricesTable = CustomerSegmentPriceModel::getTableName();
        $customerSegmentsTable = CustomerSegmentModel::getTableName();
        $customersInSegmentsTable = CustomersInSegmentsModel::getTableName();

        $usersTable = 'wp_users';
        $query = $wpdb->prepare(
            "SELECT 
                *
                FROM 
                    {$segmentPricesTable} sp
                INNER JOIN 
                    {$customerSegmentsTable} cs ON sp.customer_segment_id = cs.id
                INNER JOIN 
                    {$customersInSegmentsTable} cis ON cis.customer_segment_id = cs.id
                INNER JOIN 
                    {$usersTable} u ON cis.customer_id = u.ID
                WHERE 
                    sp.product_id = %d AND sp.customer_segment_id = %d AND sp.from_qty <= %d
                ORDER BY 
                    sp.from_qty DESC
                LIMIT 1",
            $productId, $customerSegmentId, $qty
        );

        return $wpdb->get_row($query);
    }
}
