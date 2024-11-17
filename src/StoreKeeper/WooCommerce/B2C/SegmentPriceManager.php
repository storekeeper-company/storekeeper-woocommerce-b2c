<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentModel;
use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentPriceModel;

class SegmentPriceManager
{
    public function __construct()
    {
        add_filter('woocommerce_get_price_html', [$this, 'adjustProductPriceBasedOnQty'], 10, 2);
    }

    /**
     * @return null
     */
    public static function getSegmentPrice($productId, $userId, $qty)
    {
        $findSegments = CustomerSegmentModel::findByUserId($userId);

        if (!empty($findSegments)) {
            $prices = [];
            foreach ($findSegments as $findSegment) {
                $findSegmentPrice = CustomerSegmentPriceModel::findByCustomerSegmentId($productId, $findSegment->customer_segment_id, $qty);

                if ($findSegmentPrice) {
                    $prices[] = $findSegmentPrice;
                }
            }

            if (!empty($prices)) {
                $price = $prices[0];
            } else {
                $price = null;
            }
        } else {
            $price = null;
        }

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
        $userId = get_current_user_id();
        $productId = $product->get_id();
        $qty = isset($_REQUEST['quantity']) ? intval($_REQUEST['quantity']) : 2;

        $regularPrice = $product ? wc_price($product->get_price()) : null;
        $segmentPrice = self::getSegmentPrice($productId, $userId, $qty);

        if (null !== $regularPrice) {
            $priceHtml = wc_price($segmentPrice);
        } else {
            $priceHtml = $regularPrice;
        }

        return $priceHtml;
    }
}
