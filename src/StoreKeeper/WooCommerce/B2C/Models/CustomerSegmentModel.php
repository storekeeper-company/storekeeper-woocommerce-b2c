<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;

class CustomerSegmentModel extends AbstractModel implements IModelPurge
{
    public const TABLE_NAME = 'storekeeper_customer_segments';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'name' => true,
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false,
        ];
    }

    public static function findByUserId($userId)
    {
        global $wpdb;

        $customerSegmentsTable = CustomerSegmentModel::getTableName();
        $customersInSegmentsTable = CustomersInSegmentsModel::getTableName();

        $usersTable = 'wp_users';
        $query = $wpdb->prepare(
            "SELECT 
                    cs.id AS customer_segment_id
                FROM 
                    {$customerSegmentsTable} cs
                INNER JOIN 
                    {$customersInSegmentsTable} cis ON cis.customer_segment_id = cs.id
                INNER JOIN 
                    {$usersTable} u ON cis.customer_id = u.ID
                WHERE 
                    u.ID = %d;",
            $userId,
        );

        return $wpdb->get_results($query);
    }
}
