<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\I18N;

class OrderListHandler implements WithHooksInterface
{
    public function registerHooks(): void
    {
        add_action('wp_ajax_add_order_products_to_wishlist', [$this, 'addOrderProductsToWishlist']);
        add_action('wp_ajax_nopriv_add_order_products_to_wishlist', [$this, 'addOrderProductsToWishlist']);
    }

    public function addOrderProductsToWishlist(): void
    {
        if (is_user_logged_in()) {
            $orderId = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
            $wishlistId = isset($_POST['wishlist_id']) ? intval($_POST['wishlist_id']) : 0;

            if (!$orderId) {
                error_log('Invalid order ID');
                wp_send_json_error(
                    [
                        'message' => esc_html__('Invalid order ID.', I18N::DOMAIN),
                    ]
                );
            }

            $order = wc_get_order($orderId);
            if (!$order) {
                error_log('Order not found: ' . $orderId);
                wp_send_json_error(
                    [
                        'message' => esc_html__('Order not found', I18N::DOMAIN),
                    ]
                );
            }

            $orderItems = $order->get_items();
            if (empty($orderItems)) {
                error_log('No products found in order: ' . $orderId);
                wp_send_json_error(
                    [
                        'message' => esc_html__('No products found in this order.', I18N::DOMAIN),
                    ]
                );
            }

            foreach ($orderItems as $item) {
                $productId = $item->get_product_id();
                $product = wc_get_product($productId);
                $quantity = $item->get_quantity();

                if (!$product) {
                    error_log('Product not found: ' . $productId);
                    continue;
                }
                (new WishlistHandler)->addProductsToExistingWishlistWithQty($wishlistId, $productId, $quantity);
            }

            wp_send_json_success(
                [
                    'message' => esc_html__('Products added to wishlist.'),
                ]
            );
        }
    }
}