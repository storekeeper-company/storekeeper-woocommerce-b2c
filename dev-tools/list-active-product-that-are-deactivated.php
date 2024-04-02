<?php

require_once __DIR__.'/Guard.php';
$G = new Guard('listActiveProductThatAreDeactivated');
if (!$G->lock()) {
    exit('Locked!');
}

require_once __DIR__.'/WordPressHelpers.php';

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

if (!WordPressHelpers::isCli()) {
    exit;
}

WordPressHelpers::setupAndRequireWordpress();

if (!WordPressHelpers::isStoreKeeperConnected()) {
    exit('No Backoffice API connected.');
}

// Get all product from the backend, get there shop product id's

$backend_products = fetchBackendProducts();

// Get all product from wooCommerce with G1 id and there status
$woocommerce_products = fetchWooCommerceProducts();

// Check which products are active, but not returned by the backend
$active_in_WC = 0;
foreach ($woocommerce_products as $woocommerce_product) {
    if ($woocommerce_product['enabled']) {
        ++$active_in_WC;
    }
}
$active_in_StoreKeeper = count($backend_products);

$product_active_in_wc_but_not_in_sk = 0;
$product_active_in_wc_but_not_in_sk_g1ids = [];
$product_active_in_wc_but_not_in_sk_post_ids = [];
foreach ($woocommerce_products as $shop_product_id => $woocommerce_product) {
    if ($woocommerce_product['enabled'] && !key_exists($shop_product_id, $backend_products)) {
        ++$product_active_in_wc_but_not_in_sk;
        $product_active_in_wc_but_not_in_sk_g1ids[] = $shop_product_id;
        $product_active_in_wc_but_not_in_sk_post_ids[] = $woocommerce_product['post_id'];
    }
}
$product_active_in_sk_but_not_in_WC = 0;
$product_active_in_sk_but_not_in_WC_shop_prod_ids = [];
$product_active_in_sk_but_not_in_WC_prod_ids = [];

foreach ($backend_products as $shop_product_id => $product_id) {
    if (!key_exists($shop_product_id, $woocommerce_products) || !$woocommerce_products[$shop_product_id]['enabled']) {
        ++$product_active_in_sk_but_not_in_WC;
        $product_active_in_sk_but_not_in_WC_shop_prod_ids[] = $shop_product_id;
        $product_active_in_sk_but_not_in_WC_prod_ids[] = $product_id;
    }
}
$correct_number_active_on_both = 0;
foreach ($woocommerce_products as $shop_product_id => $woocommerce_product) {
    if ($woocommerce_product['enabled'] && key_exists($shop_product_id, $backend_products)) {
        ++$correct_number_active_on_both;
    }
}

$g1ids = join(',', $product_active_in_wc_but_not_in_sk_g1ids);
$postIds = join(',', $product_active_in_wc_but_not_in_sk_post_ids);
$shopProdIds = join(',', $product_active_in_sk_but_not_in_WC_shop_prod_ids);
$prodIds = join(',', $product_active_in_sk_but_not_in_WC_prod_ids);
echo "
===================================
Active products in WC: $active_in_WC
Active products in StoreKeeper: $active_in_StoreKeeper
Active products on both: $correct_number_active_on_both
Products active in WC but not in StoreKeeper : $product_active_in_wc_but_not_in_sk
Products active in StoreKeeper but not in WC : $product_active_in_sk_but_not_in_WC

> Products active in WC but not in StoreKeeper
Shop product ids: $g1ids
Woocommerce post_ids: $postIds

> Products active in StoreKeeper but not in WC
Shop product ids: $shopProdIds
Product ids: $prodIds
===================================".PHP_EOL;

writeToFile('product_active_in_wc_but_not_in_storeKeeper_g1ids', $product_active_in_wc_but_not_in_sk_g1ids);
writeToFile('product_active_in_wc_but_not_in_storeKeeper_post_ids', $product_active_in_wc_but_not_in_sk_post_ids);
writeToFile('product_active_in_storeKeeper_but_not_in_WC_shop_prod_ids', $product_active_in_sk_but_not_in_WC_shop_prod_ids);
writeToFile('product_active_in_storeKeeper_but_not_in_WC_prod_ids', $product_active_in_sk_but_not_in_WC_prod_ids);

function writeToFile($name, $data)
{
    if (!file_exists(__DIR__.'/output')) {
        mkdir(__DIR__.'/output');
    }
    file_put_contents(__DIR__."/output/$name", json_encode($data));
}

function fetchWooCommerceProducts()
{
    global $wpdb;
    $woocommerce_products = [];

    $sql = <<<SQL
    SELECT posts.post_status as status, meta.meta_value as shop_product_id, posts.ID as post_id
    FROM {$wpdb->prefix}posts as posts
    INNER JOIN {$wpdb->prefix}postmeta as meta
    ON posts.ID=meta.post_id
    WHERE posts.post_status IN ("publish", "pending", "draft", "auto-draft", "future", "private", "inherit")
    AND meta.meta_key="storekeeper_id"
    AND posts.post_type IN ("product", "product_variation")
SQL;

    $response = $wpdb->get_results($sql);

    foreach ($response as $woocommerce_product) {
        $woocommerce_products[$woocommerce_product->shop_product_id] = [
            'enabled' => 'publish' === $woocommerce_product->status,
            'post_id' => $woocommerce_product->post_id,
        ];
    }

    return $woocommerce_products;
}

/**
 * @return array
 *
 * @throws Exception
 */
function fetchBackendProducts()
{
    $api = StoreKeeperApi::getApiByAuthName();
    $ShopModule = $api->getModule('ShopModule');

    $backend_products = [];
    $can_fetch_more = true;
    $start = 0;
    $limit = 250;
    $filters = [];

    if (StoreKeeperOptions::exists(StoreKeeperOptions::MAIN_CATEGORY_ID) && StoreKeeperOptions::get(
        StoreKeeperOptions::MAIN_CATEGORY_ID
    ) > 0) {
        $cat_id = StoreKeeperOptions::get(StoreKeeperOptions::MAIN_CATEGORY_ID);
        $filters[] = [
            'name' => 'flat_product/category_ids__overlap',
            'multi_val' => [$cat_id],
        ];
        WordPressHelpers::echoLogs("Fetching with category restriction: $cat_id");
    }

    while ($can_fetch_more) {
        WordPressHelpers::echoLogs(
            'Fetching...',
            [
                0,
                0,
                $start,
                $limit,
                [
                    [
                        'name' => 'id',
                        'dir' => 'asc',
                    ],
                ],
                $filters,
            ]
        );
        $response = $ShopModule->naturalSearchShopFlatProducts(
            0,
            0,
            $start,
            $limit,
            [
                [
                    'name' => 'id',
                    'dir' => 'asc',
                ],
            ],
            $filters
        );
        $data = $response['data'];
        $count = $response['count'];
        $total = $response['total'];
        foreach ($data as $item) {
            $backend_products[$item['id']] = $item['product_id'];
        }
        $start = $start + $limit;
        $can_fetch_more = $count >= $limit;
        if ($can_fetch_more) {
            WordPressHelpers::echoLogs("$start/$total fetched");
        } else {
            WordPressHelpers::echoLogs("$start/$total fetched, DONE!");
        }
    }

    return $backend_products;
}
