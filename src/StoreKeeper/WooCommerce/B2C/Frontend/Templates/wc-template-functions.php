<?php

/*
 * overrides the default function from WooCommerce to make sure the description and summary work correctly.
 */
if (!function_exists('woocommerce_taxonomy_archive_description')) {
    /**
     * Show an archive description on taxonomy archives.
     *
     * @see woocommerce_taxonomy_archive_description
     */
    function woocommerce_taxonomy_archive_description()
    {
        if (is_product_taxonomy() && 0 === absint(get_query_var('paged'))) {
            $term = get_queried_object();

            if ($term) {
                $summary = get_term_meta($term->term_id, 'category_summary', true);
                if (empty($summary)) {
                    // Uses term_meta description to have non-parsed description.
                    // term->description strips almost all term description's html.
                    $summary = get_term_meta($term->term_id, 'category_description', true);
                    empty($summary) ? $summary = $term->description : null;
                }

                // Echo the term description if the summary is not empty.
                if (!empty($summary)) {
                    echo '<div class="term-description">'.do_shortcode($summary).'</div>';
                }
            }
        }
    }
}

/*
 * Additional function to add the summary at the bottom of the product categories.
 */
if (!function_exists('woocommerce_taxonomy_archive_summary')) {
    /**
     * Show an archive summary on taxonomy archives.
     */
    function woocommerce_taxonomy_archive_summary()
    {
        if (is_product_taxonomy() && 0 === absint(get_query_var('paged'))) {
            $term = get_queried_object();

            if ($term) {
                $summary = get_term_meta($term->term_id, 'category_summary', true);
                // Uses term_meta description to have non-parsed description.
                // term->description strips almost all term description's html.
                $description = get_term_meta($term->term_id, 'category_description', true);
                empty($description) ? $description = $term->description : null;

                if (!empty($summary) && !empty($description)) {
                    echo '<div class="term-bottom-description">'.do_shortcode(
                            $description
                        ).'</div>'; // WPCS: XSS ok.
                }
            }
        }
    }
}

/*
 * Executes the shortcode before its getting on the page.
 * Reason for this being is that WordPress adds HTML tags to the element
 */
if (!function_exists('woocommerce_markdown_description')) {
    function woocommerce_markdown_description($content)
    {
        if (is_product()) {
            $product = wc_get_product(); // Gets the current product

            return do_shortcode($product->get_description());
        }

        return $content;
    }
}

/*
 * Executes the shortcode before its getting on the page.
 * Reason for this being is that WordPress adds HTML tags to the element
 */
if (!function_exists('woocommerce_markdown_short_description')) {
    function woocommerce_markdown_short_description($excerpt)
    {
        if (is_product()) {
            $product = wc_get_product(); // Gets the current product

            return do_shortcode($product->get_short_description());
        }

        return $excerpt;
    }
}
