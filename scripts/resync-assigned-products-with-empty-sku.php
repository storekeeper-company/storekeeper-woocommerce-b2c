<?php

function isCli()
{
    if ('cli' !== php_sapi_name()) {
        http_response_code(403);
        exit;
    }
}

isCli();

require_once __DIR__.'/WordPressHelpers.php';

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

echo 'Starting WordPress'.PHP_EOL;
WordPressHelpers::setupAndRequireWordpress();
echo 'Started WordPress'.PHP_EOL;

if (!StoreKeeperOptions::isConnected()) {
    exit();
}

$wpQuery = new WP_Query(
    [
        'posts_per_page' => -1,
        'post_type' => 'product',
    ]
);

$storekeeper_api = StoreKeeperApi::getApiByAuthName();

while ($wpQuery->have_posts()) {
    /*
     * @var $product WC_Product_Variation/WC_product
     */
    $wpQuery->the_post(); // setup post

    $product = new WC_Product(get_the_ID());

    // Get all assigned products of that configurable product to check if the sku is empty.
    $product_id_with_empty_skus = getChildrenWithEmptySkus($product->get_id());

    echo 'Searched '.$product->get_title().' for empty childen, found '.count(
            $product_id_with_empty_skus
        ).PHP_EOL;

    if (count($product_id_with_empty_skus) > 0) {
        // Trying to get the shop_product_id using the product data;
        $shop_product_id = getShopProductId($product);
        if ($shop_product_id) {
            echo 'Found product with sku: '.$product->get_sku().' Title: '.$product->get_title(
                ).' and post_id: '.$product->get_id().PHP_EOL;

            $ShopModule = $storekeeper_api->getModule('ShopModule');
            $configurable_options_data = $ShopModule->getConfigurableShopProductOptions($shop_product_id, null);
            $configurable_options = new Dot($configurable_options_data);

            $product_data_based_on_attribute_option_id = [];
            foreach ($configurable_options->get('configurable_associated_shop_products') as $assigned_product_data) {
                $assigned_product = new Dot($assigned_product_data);
                $attribute_id = $assigned_product->get('configurable_associated_product.attribute_option_ids')[0];
                $product_data_based_on_attribute_option_id[$attribute_id] = [
                    'sku' => $assigned_product->get('shop_product.product.sku'),
                    'id' => $assigned_product->get('shop_product.id'),
                    'ppu_wt' => $assigned_product->get('shop_product.product_price.ppu_wt'),
                ];
            }

            /**
             * Getting the attribute id, with a combined key that is the attribute_name + attribute_option_name.
             */
            $attribute_option_id_base_on_attribute_name_and_option_value = [];
            $attribute_name = $configurable_options->get('attributes')[0]['name'];
            foreach ($configurable_options->get('attribute_options') as $attribute_option) {
                $attribute_options_value = $attribute_option['name'];
                $attribute_option_id_base_on_attribute_name_and_option_value["$attribute_name::$attribute_options_value"] = $attribute_option['id'];
            }

            foreach ($product_id_with_empty_skus as $variation_product_id) {
                $product_variation = new WC_Product_Variation($variation_product_id);
                $attr = $product_variation->get_variation_attributes();

                if (1 === count($attr)) {
                    $key = array_keys($attr)[0];
                    $key = substr($key, strlen('attribute_pa_'));
                    $value = array_values($attr)[0];
                    $attribute_option_id = $attribute_option_id_base_on_attribute_name_and_option_value[$key.'::'.$value];

                    if ($attribute_option_id) {
                        $product_data = $product_data_based_on_attribute_option_id[$attribute_option_id];

                        if ($product_data) {
                            update_post_meta($product_variation->get_id(), 'storekeeper_id', $product_data['id']);
                            try {
                                $product_variation->set_sku($product_data['sku']);
                                $product_variation->save();
                            } catch (WC_Data_Exception $WC_Data_Exception) {
                                if (
                                    'Ongeldige of dubbele SKU.' === $WC_Data_Exception->getMessage() ||
                                    'Invalid or duplicated SKU.' === $WC_Data_Exception->getMessage()
                                ) {
                                    echo "Found duplicate sku: ({$product_data['sku']})".PHP_EOL;

                                    // Remove products with duplicate sku's
                                    $product_to_delete = getProductVariationBySku($product_data['sku']);
                                    if (false !== $product_to_delete) {
                                        wp_trash_post($product_to_delete->ID);
                                        echo "Deleted duplicate sku product: ({$product_to_delete->ID})".PHP_EOL;
                                    }

                                    $product_variation->set_sku($product_data['sku']);
                                    $product_variation->set_regular_price($product_data['ppu_wt']);
                                    $product_variation->save();
                                } else {
                                    throw $WC_Data_Exception;
                                }
                            }
                            echo 'Updated product '.$product->get_title(
                                ).' with sku: '.$product_data['sku'].' and shop_product_id: '.$product_data['id'].' against post id: '.$product_variation->get_id(
                                ).PHP_EOL.PHP_EOL;
                        } else {
                            echo "Could not find product data with option id: ($attribute_option_id)".PHP_EOL;
                        }
                    } else {
                        echo "Could not find attribute option with data: ($key::$value)".PHP_EOL;
                    }
                } else {
                    echo 'Found a product with more then 1 attribute'.PHP_EOL;
                }
            }
        } else {
            $shop_product_id = get_post_meta($product->get_id(), 'storekeeper_id', true);
            echo 'Could not find product with sku: '.$product->get_sku().' Title: '.$product->get_title(
                ).' OR shop_product_id: '.$shop_product_id.PHP_EOL;
        }
    }
}

/**
 * @param $sku
 *
 * @return bool
 *
 * @throws \StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException
 */
function getProductVariationBySku($sku)
{
    $products = WordpressExceptionThrower::throwExceptionOnWpError(
        get_posts(
            [
                'post_type' => 'product_variation',
                'number' => 1,
                'meta_key' => '_sku',
                'meta_value' => $sku,
                'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
            ]
        )
    );

    if (1 === count($products)) {
        return $products[0];
    }

    return false;
}

function getChildrenWithEmptySkus($parent_post_id)
{
    global $wpdb;
    $sql = <<<SQL
SELECT post.ID,meta.meta_value
FROM `{$wpdb->prefix}posts` as post
LEFT JOIN `{$wpdb->prefix}postmeta` as meta
on post.ID=meta.post_id
WHERE post.post_parent=$parent_post_id
AND meta.meta_key="_sku"
SQL;

    $response = $wpdb->get_results($sql);

    $product_id_with_empty_skus = [];
    foreach ($response as $obj) {
        $shop_product_id = get_post_meta($obj->ID, 'storekeeper_id', true);
        if (empty($obj->meta_value)) {
            $product_id_with_empty_skus[] = $obj->ID;
        } else {
            if (empty($shop_product_id)) {
                $product_id_with_empty_skus[] = $obj->ID;
            }
        }
    }

    return $product_id_with_empty_skus;
}

function getShopProductId($product)
{
    global $storekeeper_api;
    // First checking if the shop_product_id is already stored in the storekeeper_id
    $shop_product_id = get_post_meta($product->get_id(), 'storekeeper_id', true);
    if (!empty($shop_product_id)) {
        echo 'Found shop_product_id in meta data of product '.$product->get_title(
            ).' ('.$shop_product_id.')'.PHP_EOL;

        return $shop_product_id;
    }

    // Else fetch products by sku
    $ShopModule = $storekeeper_api->getModule('ShopModule');
    $response = $ShopModule->naturalSearchShopFlatProducts(
        0,
        0,
        0,
        1,
        null,
        [
            [
                'name' => 'flat_product/product/sku__=',
                'val' => $product->get_sku(),
            ],
            [
                'name' => 'flat_product/product/type__=',
                'val' => 'configurable',
            ],
        ]
    );
    if (1 === $response['total']) {
        $data = $response['data'];
        $productData = $data[0];
        echo 'Found shop_product_id by sku '.$product->get_title().' ('.$productData['id'].')'.PHP_EOL;

        return $productData['id'];
    }

    // Lastly fetch product by name.
    $ShopModule = $storekeeper_api->getModule('ShopModule');
    $response = $ShopModule->naturalSearchShopFlatProducts(
        0,
        0,
        0,
        1,
        null,
        [
            [
                'name' => 'flat_product/title__ilike',
                'val' => "%{$product->get_title()}%",
            ],
            [
                'name' => 'flat_product/product/type__=',
                'val' => 'configurable',
            ],
        ]
    );
    if (1 === $response['total']) {
        $data = $response['data'];
        $productData = $data[0];
        echo 'Found shop_product_id by title '.$product->get_title().' ('.$productData['id'].')'.PHP_EOL;

        return $productData['id'];
    }

    return null;
}

echo 'DONE!'.PHP_EOL;
