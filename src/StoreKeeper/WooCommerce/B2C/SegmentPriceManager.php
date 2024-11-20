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
            $segmentIds = array_map('intval', array_column($findSegments, 'customer_segment_id'));

            if (!empty($segmentIds)) {
                $segmentPrices = CustomerSegmentPriceModel::findByCustomerSegmentIds($productId, $segmentIds, $qty);

                if (!empty($segmentPrices)) {
                    $filteredPrices = array_filter($segmentPrices, function ($item) use ($qty) {
                        return $item->from_qty <= $qty;
                    });

                    if (!empty($filteredPrices)) {
                        usort($filteredPrices, function ($a, $b) {
                            return $b->from_qty <=> $a->from_qty;
                        });

                        return $filteredPrices[0]->ppu_wt;
                    }
                }
            }
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
        $qty = isset($_REQUEST['quantity']) ? intval($_REQUEST['quantity']) : 1;

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
