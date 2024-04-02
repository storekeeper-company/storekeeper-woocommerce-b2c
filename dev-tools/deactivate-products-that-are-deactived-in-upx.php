<?php

require_once __DIR__.'/Guard.php';

$G = new Guard('deactivateProductsThatAreDeactivedInStoreKeeper');
if (!$G->lock()) {
    exit('Locked!');
}

require_once __DIR__.'/WordPressHelpers.php';

if (count($argv) > 0 && 'yes' !== $argv[1]) {
    echo "Add \"yes\" behind this script\n";
    exit;
}

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

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

$product_active_in_wc_but_not_in_backend_post_ids = [];
foreach ($woocommerce_products as $shop_product_id => $woocommerce_product) {
    if ($woocommerce_product['enabled'] && !array_key_exists($shop_product_id, $backend_products)) {
        $product_active_in_wc_but_not_in_backend_post_ids[$shop_product_id] = $woocommerce_product['post_id'];
    }
}

foreach ($product_active_in_wc_but_not_in_backend_post_ids as $shop_product_id => $post_id) {
    WordPressHelpers::echoLogs("Disabling product with post_id:$post_id and shop prod id:$shop_product_id");
    TaskHandler::scheduleTask(
        TaskHandler::PRODUCT_DEACTIVATED,
        $shop_product_id,
        ['storekeeper_id' => $shop_product_id],
        true
    );
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
