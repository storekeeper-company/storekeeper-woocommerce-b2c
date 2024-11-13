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
            'customer_email' => false,
            'name' => true,
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false,
        ];
    }

    public function findByEmail($email)
    {
        global $wpdb;
        $query = $wpdb->prepare("SELECT * FROM " . self::getTableName() . " WHERE customer_email = %s", $email);
        return $wpdb->get_row($query);
    }
}
