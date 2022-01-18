<?php

namespace StoreKeeper\WooCommerce\B2C\Query;

class ProductQueryBuilder
{
    public static function getProductIdsByPostType(string $postType, int $index, bool $activeProductsOnly = false)
    {
        global $table_prefix, $wpdb;
        $activeCondition = '';
        if ($activeProductsOnly) {
            $activeCondition = <<<SQL
    AND post_status = "publish"
SQL;
        }
        $query = <<<SQL
    SELECT ID as product_id
    FROM {$table_prefix}posts
    WHERE post_type = %s
    {$activeCondition}
    ORDER BY ID
    LIMIT 1
    OFFSET %d;
SQL;

        return $wpdb->prepare($query, $postType, $index);
    }

    public static function getProductIdByProductType(string $type, int $index)
    {
        global $table_prefix, $wpdb;

        $query = <<<SQL
    SELECT ID as product_id
    FROM {$table_prefix}posts AS posts
    INNER JOIN {$table_prefix}term_relationships AS relationship 
        ON posts.ID = relationship.object_id
    INNER JOIN {$table_prefix}term_taxonomy AS taxonomy 
        ON relationship.term_taxonomy_id = taxonomy.term_taxonomy_id
    INNER JOIN {$table_prefix}terms AS terms 
        ON taxonomy.term_id = terms.term_id
    WHERE taxonomy.taxonomy = 'product_type'
    AND terms.slug = %s
    AND posts.post_type = 'product'
    ORDER BY ID
    LIMIT 1
        OFFSET %d;
SQL;

        return $wpdb->prepare($query, $type, $index);
    }

    public static function getCustomProductAttributes(): string
    {
        global $table_prefix;

        return <<<SQL
    SELECT post.ID, meta.meta_value AS attributes
    FROM {$table_prefix}postmeta AS meta 
    JOIN {$table_prefix}posts as post ON post.ID = meta.post_id
    WHERE post.post_type = 'product'
        AND meta.meta_key = '_product_attributes'
        AND meta.meta_value like '%"is_taxonomy";i:0;%'
SQL;
    }

    public static function getProductCount()
    {
        global $wpdb;

        return <<<SQL
    SELECT COUNT(ID)
    FROM {$wpdb->prefix}posts
    WHERE post_type IN ('product', 'product_variation')
SQL;
    }

    public static function getProductMetaDataAtIndex($index)
    {
        global $wpdb;

        return <<<SQL
SELECT meta_key, meta_value
FROM {$wpdb->prefix}postmeta as meta
WHERE post_id = (
    SELECT ID
    FROM {$wpdb->prefix}posts
    WHERE post_type IN ('product', 'product_variation')
    ORDER BY ID DESC 
    LIMIT 1
    OFFSET $index
);
SQL;
    }
}
