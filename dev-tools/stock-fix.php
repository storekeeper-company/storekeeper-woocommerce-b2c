<?php

require_once __DIR__.'/WordPressHelpers.php';

WordPressHelpers::setupAndRequireWordpress();

echo "\e[1;37mChecking product stocks...".PHP_EOL;

$woocommerce_products = fetchWooCommerceProducts();

// Update the stock status of each woocommerce product
foreach ($woocommerce_products as $product) {
    updateStockStatus($product);
}

/**
 * Updates the stock of the given post/product id.
 *
 * @param WC_Product $product The product
 */
function updateStockStatus($product)
{
    try {
        $id = $product->get_id();

        echo "Checking product with product id $id";

        changeStatus($product, 'outofstock');
        changeStatus($product, 'instock');
        echo "\033[0;32m IN STOCK, FIXING STOCK".PHP_EOL."\033[1;37m";
    } catch (Exception $e) {
        echo $e->getMessage();
        echo "\033[0;31mERROR CHECKING PRODUCT, IGNORING".PHP_EOL."\033[1;37m";
    }
}

/**
 * Changes the stock status of the product.
 *
 * @param $product
 * @param $status
 */
function changeStatus($product, $status)
{
    // Update the product stocks
    if ($product->is_type('external')) {
        // External products are always in stock.
        $product->set_stock_status($status);
    } elseif ($product->is_type('variable') && !$product->get_manage_stock()) {
        // Stock status is determined by children.
        foreach ($product->get_children() as $child_id) {
            $child = wc_get_product($child_id);
            if (!$product->get_manage_stock()) {
                $child->set_stock_status($status);
                $child->save();
            }
        }
        WC_Product_Variable::sync($product, false);
    } else {
        $product->set_stock_status($status);
    }
    $product->save();
}

/**
 * Fetch all woocommerce products that are enabled.
 *
 * @return array All woocommerce products that are enabled
 */
function fetchWooCommerceProducts()
{
    return wc_get_products(
        [
            'status' => 'publish',
            'limit' => -1,
        ]
    );
}
