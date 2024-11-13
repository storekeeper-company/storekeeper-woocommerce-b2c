<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentModel;
use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentPriceModel;

class SegmentPriceProductBack
{
    public function __construct()
    {
        add_filter('woocommerce_get_price_html', [$this, 'adjustProductPriceBasedOnQty'], 10, 2);
    }

    /**
     * @return null
     */
    public static function getSegmentPrice($productId, $customerEmail, $qty)
    {
        global $wpdb;

        $segmentPricesTable = CustomerSegmentPriceModel::getTableName();
        $customerSegmentsTable = CustomerSegmentModel::getTableName();

        $query = $wpdb->prepare(
            "SELECT sp.* 
            FROM {$segmentPricesTable} sp
            INNER JOIN {$customerSegmentsTable} cs ON sp.customer_segment_id = cs.id
            WHERE sp.product_id = %d AND cs.customer_email = %s AND sp.from_qty <= %d 
            ORDER BY sp.from_qty DESC 
            LIMIT 1",
            $productId, $customerEmail, $qty
        );

        $price = $wpdb->get_row($query);

        if ($price) {
            return $price->ppu_wt;
        }

        return null;
    }

    /**
     * @return null
     */
    public function adjustProductPriceBasedOnQty($priceHtml, $product)
    {
        $user = get_current_user_id();
        $userData = get_userdata($user);
        $userEmail = $userData->user_email;
        $customerSegment = new CustomerSegmentModel();
        $customerEmail = $customerSegment->findByEmail($userEmail);
        $productId = $product->get_id();
        $qty = isset($_REQUEST['quantity']) ? intval($_REQUEST['quantity']) : 2;

        $regularPrice = $product ? wc_price($product->get_price()) : null;
        $segmentPrice = self::getSegmentPrice($productId, $customerEmail, $qty);

        if (null !== $regularPrice) {
            $priceHtml = wc_price($segmentPrice);
        } else {
            $priceHtml = $regularPrice;
        }

        return $priceHtml;
    }
}
