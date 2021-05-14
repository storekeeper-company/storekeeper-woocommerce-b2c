<?php

use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

require_once __DIR__.'/WordPressHelpers.php';

WordPressHelpers::setupAndRequireWordpress();

echo "\e[1;37mChecking product cross/upsells...".PHP_EOL;

$woocommerce_products = fetchWooCommerceProducts();

// Total amounts
$total_upsell = 0;
$total_cross_sell = 0;

// Expected amounts
$ex_total_upsell = 0;
$ex_total_cross_sell = 0;

$not_found = 0;

$check_backend = sizeof($argv) > 1 ? '--no-backend' !== $argv[1] : true;

// Calculate the total amount of product with cross/upsell
foreach ($woocommerce_products as $product) {
    $upsell = $product->get_upsell_ids();
    $cross_sell = $product->get_cross_sell_ids();

    if (sizeof($upsell) > 0) {
        ++$total_upsell;
    }
    if (sizeof($cross_sell) > 0) {
        ++$total_cross_sell;
    }

    $shop_product_id = get_post_meta($product->get_id(), 'storekeeper_id', true);
    if ($shop_product_id && $check_backend) {
        $api = StoreKeeperApi::getApiByAuthName();
        $ShopModule = $api->getModule('ShopModule');
        $upsell_backend_ids = $ShopModule->getUpsellShopProductIds($shop_product_id);
        $cross_sell_backend_ids = $ShopModule->getCrossSellShopProductIds($shop_product_id);

        if (sizeof($upsell_backend_ids) > 0) {
            ++$ex_total_upsell;
        }
        if (sizeof($cross_sell_backend_ids) > 0) {
            ++$ex_total_cross_sell;
        }

        $backend_product = ProductImport::findBackendShopProductId($shop_product_id);
        if (false == $backend_product) {
            ++$not_found;
        }
    }
}

echo "\033[0;32mAMOUNT OF PRODUCTS WITH UPSELL $total_upsell".PHP_EOL."\033[1;37m";
echo "\033[0;32mAMOUNT OF PRODUCTS WITH CROSS SELL $total_cross_sell".PHP_EOL."\033[1;37m";

if ($check_backend) {
    echo "\033[0;32mEXPECTED AMOUNT OF PRODUCTS WITH UPSELL $ex_total_upsell".PHP_EOL."\033[1;37m";
    echo "\033[0;32mEXPECTED AMOUNT OF PRODUCTS WITH CROSS SELL $ex_total_cross_sell".PHP_EOL."\033[1;37m";

    echo "\033[0;32mNOT FOUND PRODUCTS FROM BACKEND $not_found".PHP_EOL."\033[1;37m";
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
