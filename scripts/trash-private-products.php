<?php

require_once __DIR__.'/Guard.php';
$G = new Guard('trashPrivateProducts');
if (!$G->lock()) {
    exit('Locked');
}

// Initialize WordPress
require_once __DIR__.'/WordPressHelpers.php';
WordPressHelpers::setupAndRequireWordpress();

global $wpdb;

// Fetch products that are private
$fetch_posts_sql = <<<SQL
    SELECT post.ID,post.post_title,post.post_name
    FROM `{$wpdb->prefix}posts` as post
    WHERE post.post_type in ("product","product_variation") 
    AND post.post_status = "private"
SQL;
$products = $wpdb->get_results($fetch_posts_sql, ARRAY_A);

// Trashing all products.
$count = count($products);
foreach ($products as $index => $product) {
    $prod_name = $product['post_title'];
    $prod_sku = $product['post_name'];
    $post_id = $product['ID'];
    $current_pos = $index + 1;
    $time = date('Y-m-d H:i:s');

    wp_trash_post($post_id);

    echo "[$time] [$current_pos/$count] trashed post_id:$post_id - $prod_name ($prod_sku)".PHP_EOL;
}
