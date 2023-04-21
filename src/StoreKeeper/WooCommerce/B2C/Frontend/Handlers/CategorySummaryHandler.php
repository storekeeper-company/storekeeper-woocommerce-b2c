<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Frontend\Filters\PrepareProductCategorySummaryFilter;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;

class CategorySummaryHandler implements WithHooksInterface
{
    public function registerHooks(): void
    {
        add_action('woocommerce_after_shop_loop', [$this, 'addCategorySummary'], 100);
        add_action('woocommerce_no_products_found', [$this, 'addCategorySummary'], 100);
    }

    public function addCategorySummary()
    {
        if (is_product_category() && 0 === absint(get_query_var('paged'))) {
            $term = get_queried_object();

            if ($term instanceof \WP_Term) {
                $summary = get_term_meta($term->term_id, 'category_summary', true);
                $summary = apply_filters(PrepareProductCategorySummaryFilter::getTag(), $summary, $term);

                if (!empty($summary)) {
                    echo '<div class="term-bottom-description">'.do_shortcode($summary).'</div>'; // WPCS: XSS ok.
                }
            }
        }
    }
}
