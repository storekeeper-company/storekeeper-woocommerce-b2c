<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;

class Categories
{
    public const TAXONOMY_PRODUCT_CATEGORY = 'product_cat';

    /**
     * Getting the category term by slug.
     *
     * @return array|false|\WP_Term
     */
    public static function getCategoryBySlug($slug)
    {
        return get_term_by('slug', $slug, self::TAXONOMY_PRODUCT_CATEGORY);
    }

    /**
     * Getting the category term by Backoffice category_id.
     *
     * @param string $slug Is not required, But could yield more stable results
     *
     * @return array|bool|\WP_Error|\WP_Term|null
     *
     * @throws WordpressException
     */
    public static function getCategoryById($category_id, $slug = '')
    {
        $categories = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => self::TAXONOMY_PRODUCT_CATEGORY,
                    'slug' => $slug,
                    'hide_empty' => false,
                    'number' => 1,
                    'meta_query' => [
                        [
                            'key' => 'storekeeper_id',
                            'value' => $category_id,
                            'compare' => '=',
                        ],
                    ],
                ]
            )
        );

        if (1 === count($categories)) {
            return get_term(
                array_shift($categories),
                self::TAXONOMY_PRODUCT_CATEGORY
            );
        }

        return false;
    }

    /**
     * @return bool
     *
     * @throws WordpressException
     */
    public static function deleteCategoryByTermId($term_id)
    {
        return (bool) WordpressExceptionThrower::throwExceptionOnWpError(
            wp_delete_term($term_id, self::TAXONOMY_PRODUCT_CATEGORY)
        );
    }
}
