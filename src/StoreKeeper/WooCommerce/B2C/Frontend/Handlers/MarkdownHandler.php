<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

class MarkdownHandler implements \StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface
{
    public function registerHooks(): void
    {
        add_filter('the_content', [$this, 'parseDescription']);
        add_filter('woocommerce_short_description', [$this, 'parseShortDescription']);
    }

    public function parseDescription($content)
    {
        if (is_product()) {
            $product = wc_get_product(); // Gets the current product
            if ($product instanceof \WC_Product) {
                return do_shortcode($product->get_description());
            }
        }

        return $content;
    }

    public function parseShortDescription($excerpt)
    {
        if (is_product()) {
            $product = wc_get_product(); // Gets the current product
            if ($product instanceof \WC_Product) {
                return do_shortcode($product->get_short_description());
            }
        }

        return $excerpt;
    }
}
