<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

class ProductHelper
{
    /**
     * Get the total amount of products currently in WooCommerce.
     */
    public static function getAmountOfProductsInWooCommerce(): int
    {
        global $wpdb;
        $query =
            "
				SELECT COUNT(posts.ID)
				FROM {$wpdb->posts} as posts
				WHERE
				    posts.post_type IN ( 'product', 'product_variation' )
				    AND posts.post_status != 'trash'
				"
        ;

        return $wpdb->get_var($query);
    }

    public static function getAmountOfProductsWithoutSku(): int
    {
        global $wpdb;
        $query =
            "
				SELECT COUNT(posts.ID)
				FROM {$wpdb->posts} as posts
				INNER JOIN {$wpdb->wc_product_meta_lookup} AS lookup ON posts.ID = lookup.product_id
				WHERE
				    posts.post_type IN ( 'product' )
				    AND posts.post_status != 'trash'
				    AND posts.ID = lookup.product_id
				    AND (lookup.sku IS NULL OR TRIM(lookup.sku) = '')
				"
        ;

        return $wpdb->get_var($query);
    }

    public static function getAmountOfProductVariationsWithoutSku(): int
    {
        global $wpdb;
        $query =
            "
				SELECT COUNT(posts.ID)
				FROM {$wpdb->posts} as posts
				INNER JOIN {$wpdb->wc_product_meta_lookup} AS lookup ON posts.ID = lookup.product_id
				WHERE
				    posts.post_type IN ( 'product_variation' )
				    AND posts.post_status != 'trash'
				    AND posts.ID = lookup.product_id
				    AND (lookup.sku IS NULL OR TRIM(lookup.sku) = '')
				"
        ;

        return $wpdb->get_var($query);
    }

    public static function getProductsIdsWithoutSku(): array
    {
        global $wpdb;
        $query =
            "
				SELECT posts.ID
				FROM {$wpdb->posts} as posts
				INNER JOIN {$wpdb->wc_product_meta_lookup} AS lookup ON posts.ID = lookup.product_id
				WHERE
				    posts.post_type IN ( 'product', 'product_variation' )
				    AND posts.post_status != 'trash'
				    AND posts.ID = lookup.product_id
				    AND (lookup.sku IS NULL OR TRIM(lookup.sku) = '')
                ORDER BY posts.ID
				"
        ;

        return $wpdb->get_col($query);
    }
}
