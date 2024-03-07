<?php

require_once __DIR__.'/Guard.php';
$G = new Guard('hideProductsThatArePrivate');
if (!$G->lock()) {
    exit('Locked');
}

$process_items = false;
if (array_key_exists(1, $argv)) {
    $process_items = true;
}

require_once __DIR__.'/WordPressHelpers.php';

WordPressHelpers::setupAndRequireWordpress();

global $wpdb;

// Fetch items that needs processing
$fetch_posts_sql = <<<SQL
    SELECT post.ID,post.post_title,post.post_name
    FROM `{$wpdb->prefix}posts` as post
    WHERE post.post_type in ("product","product_variation") 
    AND post.post_status = "private"
SQL;
$products = $wpdb->get_results($fetch_posts_sql, ARRAY_A);

// Getting the 'term_taxonomy_id' of terms with the slug 'exclude-from-catalog' and 'exclude-from-search'
$sql = <<<SQL
SELECT t.term_id,t.slug,tt.term_taxonomy_id
FROM {$wpdb->prefix}terms as t, {$wpdb->prefix}term_taxonomy as tt
WHERE slug in ("exclude-from-catalog","exclude-from-search")
AND t.term_id=tt.term_id
SQL;

$results = $wpdb->get_results($sql);
$exclude_from_catalog_id = 0;
$exclude_from_search_id = 0;
foreach ($results as $result) {
    if ('exclude-from-catalog' === $result->slug) {
        $exclude_from_catalog_id = $result->term_taxonomy_id;
    } else {
        if ('exclude-from-search' === $result->slug) {
            $exclude_from_search_id = $result->term_taxonomy_id;
        }
    }
}

// Checking if the ids are found / set
if ($exclude_from_catalog_id <= 0) {
    throw new Exception('Could not find exclude_from_catalog_id: '.$exclude_from_catalog_id);
}

if ($exclude_from_search_id <= 0) {
    throw new Exception('Could not find exclude_from_search_id: '.$exclude_from_search_id);
}

// Display items when $process_items is false
if (!$process_items) {
    $count = count($products);
    foreach ($products as $index => $product) {
        $prod_name = $product['post_title'];
        $prod_sku = $product['post_name'];
        $post_id = $product['ID'];
        $current_pos = $index + 1;

        $sql = <<<SQL
SELECT term_taxonomy_id
FROM {$wpdb->prefix}term_relationships 
WHERE object_id=%d 
AND term_taxonomy_id in (%d,%d)
SQL;
        $results = $wpdb->get_results(
            $wpdb->prepare($sql, $post_id, $exclude_from_catalog_id, $exclude_from_search_id),
            ARRAY_A
        );

        echo '['.date(
            'Y-m-d H:i:s'
        ).'] '."[$current_pos/$count] post_id:$post_id - $prod_name ($prod_sku)".' '.json_encode(
            $results
        ).PHP_EOL;
    }
// Process items when $process_items is true
} else {
    foreach ($products as $index => $product) {
        $post_id = $product['ID'];

        // Check if exclude_from_catalog is already set
        $exclude_catalog_exists_sql = <<<SQL
SELECT term_taxonomy_id
FROM {$wpdb->prefix}term_relationships 
WHERE object_id=%d 
AND term_taxonomy_id = %d
SQL;
        $exclude_catalog_exists = false;
        $exclude_catalog_exists_results = $wpdb->get_results(
            $wpdb->prepare($exclude_catalog_exists_sql, $post_id, $exclude_from_catalog_id)
        );
        if (count($exclude_catalog_exists_results) > 0) {
            $exclude_catalog_exists = true;
        }

        // Check if exclude_from_search is already set
        $exclude_search_exists_sql = <<<SQL
SELECT term_taxonomy_id
FROM {$wpdb->prefix}term_relationships 
WHERE object_id=%d 
AND term_taxonomy_id = %d
SQL;
        $exclude_search_exists = false;
        $exclude_search_exists_results = $wpdb->get_results(
            $wpdb->prepare($exclude_search_exists_sql, $post_id, $exclude_from_search_id)
        );
        if (count($exclude_search_exists_results) > 0) {
            $exclude_search_exists = true;
        }

        // Set exclude_from_catalog if it isn't already
        if (!$exclude_catalog_exists) {
            $exclude_catalog_insert_sql = <<<SQL
INSERT INTO {$wpdb->prefix}term_relationships
(object_id, term_taxonomy_id, term_order)
VALUES
(%d, %d, 0)
SQL;
            $wpdb->query($wpdb->prepare($exclude_catalog_insert_sql, $post_id, $exclude_from_catalog_id));
        }

        // Set exclude_from_search if it isn't already
        if (!$exclude_search_exists) {
            $exclude_search_insert_sql = <<<SQL
INSERT INTO {$wpdb->prefix}term_relationships
(object_id, term_taxonomy_id, term_order)
VALUES
(%d, %d, 0)
SQL;
            $wpdb->query($wpdb->prepare($exclude_search_insert_sql, $post_id, $exclude_from_search_id));
        }
    }

    // Updating the count for 'exclude-from-search' and 'exclude-from-catalog' in the wp_term_taxonomy table
    $updateCountSql = <<<SQL
UPDATE {$wpdb->prefix}term_taxonomy as tt
SET count = (
    SELECT COUNT(tr.term_taxonomy_id)
    FROM {$wpdb->prefix}term_relationships as tr
    WHERE tr.term_taxonomy_id=%d
)
WHERE tt.term_taxonomy_id=%d
SQL;

    // Updates the count for exclude-from-search
    $wpdb->query($wpdb->prepare($updateCountSql, $exclude_from_search_id, $exclude_from_search_id));
    // Updates the count for exclude-from-catalog
    $wpdb->query($wpdb->prepare($updateCountSql, $exclude_from_catalog_id, $exclude_from_catalog_id));
}
