<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

class ProductHelper
{
    /**
     * Get the total amount of products currently in WooCommerce.
     *
     * @return int
     */
    public static function getAmountOfProductsInWooCommerce()
    {
        // Get all products currently in WooCommerce
        $products = wc_get_products(['status' => 'publish', 'limit' => -1]);
        $count = sizeof($products);

        // Remove the products out of memory so we don't use too much
        unset($products);

        return $count;
    }
}
