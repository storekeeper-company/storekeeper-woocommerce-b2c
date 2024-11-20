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

    public static function findByCustomerSegmentIds($productId, $customerSegmentIds, $qty)
    {
        global $wpdb;
        $segmentPricesTable = CustomerSegmentPriceModel::getTableName();
        $customerSegmentsTable = CustomerSegmentModel::getTableName();
        $customersInSegmentsTable = CustomersInSegmentsModel::getTableName();
        $usersTable = $wpdb->prefix . 'users';
        $placeholders = implode(',', array_fill(0, count($customerSegmentIds), '%d'));

        $query = $wpdb->prepare(
            "SELECT * FROM 
                    {$segmentPricesTable} sp
                INNER JOIN 
                    {$customerSegmentsTable} cs ON sp.customer_segment_id = cs.id
                INNER JOIN 
                    {$customersInSegmentsTable} cis ON cis.customer_segment_id = cs.id
                INNER JOIN 
                    {$usersTable} u ON cis.customer_id = u.ID
                WHERE 
                    sp.product_id = %d 
                    AND sp.customer_segment_id IN ($placeholders) 
                    AND sp.from_qty <= %d
                ORDER BY 
                    sp.ppu_wt ASC, sp.from_qty DESC",
            array_merge([$productId], $customerSegmentIds, [$qty])
        );

        return $wpdb->get_results($query);
    }
}
