<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentModel;
use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentPriceModel;
use StoreKeeper\WooCommerce\B2C\Models\CustomersInSegmentsModel;
use StoreKeeper\WooCommerce\B2C\SegmentPriceManager;

class CustomerSegmentHandler implements WithHooksInterface
{
    public function registerHooks(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueCustomQuantityScript']);
        add_action('wp_enqueue_scripts', [$this, 'showProductOnProductPage']);
        add_action('wp_enqueue_scripts', [$this, 'showProductOnCart']);
        add_action('wp_ajax_adjust_price_based_on_quantity', [$this, 'adjustPriceBasedOnQuantityAjax']);
        add_filter('woocommerce_before_calculate_totals', [$this, 'adjustCartItemPriceBasedOnQuantity']);
        add_action('woocommerce_new_order', [$this, 'checkPriceMismatchOnCheckout']);
        add_action('woocommerce_checkout_update_order_meta', [$this, 'saveCustomerSegmentAsOrderMeta']);
    }

    public function enqueueCustomQuantityScript(): void
    {
        $jsUrl = plugins_url('storekeeper-for-woocommerce/resources/js/frontend/price.js');

        wp_enqueue_script(
            'custom-quantity-script',
            $jsUrl,
            ['jquery'],
            null,
            true
        );

        wp_localize_script('custom-quantity-script', 'ajax_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    public function adjustPriceBasedOnQuantityAjax(): void
    {
        if (!isset($_POST['quantity']) || !isset($_POST['product_id'])) {
            error_log('Missing parameters in AJAX request.');
            wp_send_json_error(['message' => 'Missing parameters']);
        }

        $quantity = intval($_POST['quantity']);
        $productId = intval($_POST['product_id']);

        $userId = get_current_user_id();

        if ($userId) {
            $segmentPrice = SegmentPriceManager::getSegmentPrice($productId, $userId, $quantity);

            $product = wc_get_product($productId);
            $regularPrice = $product ? wc_price($product->get_price()) : null;

            if (null !== $segmentPrice) {
                wp_send_json_success(['new_price' => wc_price($segmentPrice)]);
            } else {
                wp_send_json_success(['new_price' => $regularPrice]);
            }
        }
    }

    public function adjustCartItemPriceBasedOnQuantity($cart): void
    {
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }

        $userId = get_current_user_id();

        if ($userId) {
            foreach ($cart->get_cart() as $cartItemKey => $cartItem) {
                $productId = isset($cartItem['variation_id']) && 0 != $cartItem['variation_id']
                    ? $cartItem['variation_id']
                    : $cartItem['product_id'];
                $quantity = $cartItem['quantity'];
                $adjustedPrice = SegmentPriceManager::getSegmentPrice($productId, $userId, $quantity);
                if (null !== $adjustedPrice) {
                    $cartItem['data']->set_price($adjustedPrice);
                }
            }
        }
    }

    /**
     * @throws WordpressException
     */
    public function checkPriceMismatchOnOrder($orderId): void
    {
        $order = wc_get_order($orderId);
        $userId = get_current_user_id();

        if ($userId) {
            foreach ($order->get_items() as $itemId => $item) {
                $product = $item->get_product();
                $quantity = $item->get_quantity();

                $segmentPrice = SegmentPriceManager::getSegmentPrice($product->get_id(), $userId, $quantity);
                $standardPrice = self::getStandardPrice($product);
                $appliedPrice = $item->get_total() / $quantity;

                if (isset($segmentPrice) && $segmentPrice !== $appliedPrice) {
                    LoggerFactory::createErrorTask('no-customer-price-on-checkout',
                        new \Exception('Price mismatch detected during checkout'),
                        [
                            'customer_email' => $order->get_billing_email(),
                            'product_id' => $product->get_id(),
                            'product_name' => $product->get_name(),
                            'segment_price' => $segmentPrice,
                            'standard_price' => $standardPrice,
                            'applied_price' => $appliedPrice,
                            'quantity' => $quantity,
                        ]
                    );

                    $admin_email = get_option('admin_email');
                    $subject = 'Price Mismatch Detected';
                    $message = sprintf(
                        'A mismatch was detected between the segment price and the applied price at checkout for product: %s (ID: %d). Segment Price: €%s vs Applied Price: €%s. Customer Email: %s, Quantity: %d',
                        $product->get_name(),
                        $product->get_id(),
                        $segmentPrice,
                        $appliedPrice,
                        $order->get_billing_email(),
                        $quantity
                    );
                    wp_mail($admin_email, $subject, $message);
                }
            }
        }
    }

    /**
     * @return float|mixed
     */
    public function getStandardPrice($product)
    {
        if ($product->is_type('variable')) {
            $defaultVariation = $product->get_available_variations()[0];

            return $defaultVariation['display_price'];
        }

        return (float) $product->get_regular_price();
    }

    /**
     * @throws WordpressException
     */
    public function checkPriceMismatchOnCheckout($orderId): void
    {
        self::checkPriceMismatchOnOrder($orderId);
        self::saveCustomerSegmentAsOrderMeta($orderId);
    }

    public function saveCustomerSegmentAsOrderMeta($orderId): void
    {
        global $wpdb;

        $order = wc_get_order($orderId);
        $customerEmail = $order->get_billing_email();
        $user = get_user_by('email', $customerEmail);
        $userId = $user->ID;

        foreach ($order->get_items() as $itemId => $item) {
            $productId = $item->get_product_id();
            $qty = $item->get_quantity();
            $segmentPricesTable = CustomerSegmentPriceModel::getTableName();
            $customerSegmentsTable = CustomerSegmentModel::getTableName();
            $customersInSegmentsTable = CustomersInSegmentsModel::getTableName();
            $usersTable = $wpdb->prefix . 'users';

            $sql = $wpdb->prepare(
                "SELECT * FROM 
                    {$segmentPricesTable} sp
                INNER JOIN 
                    {$customerSegmentsTable} cs ON sp.customer_segment_id = cs.id
                INNER JOIN 
                    {$customersInSegmentsTable} cis ON cis.customer_segment_id = cs.id
                INNER JOIN 
                    {$usersTable} u ON cis.customer_id = u.ID
                WHERE 
                    sp.product_id = %d AND u.ID = %d AND sp.from_qty <= %d;
             ",
                $productId, $userId, $qty
            );

            $segment = $wpdb->get_row($sql);

            if ($segment) {
                update_post_meta($orderId, '_customer_segment_id', $segment->id);
                update_post_meta($orderId, '_customer_segment_name', $segment->name);
            } else {
                update_post_meta($orderId, '_customer_segment_id', 'No segment found');
                update_post_meta($orderId, '_customer_segment_name', 'No segment found');
            }
        }
    }

    public function showProductOnProductPage(): void
    {
        global $woocommerce;

        if (!is_product()) {
            return;
        }

        $productId = get_the_ID();
        $quantity = 0;

        if ($productId) {
            $userId = get_current_user_id();
            if ($userId) {
                foreach ($woocommerce->cart->get_cart() as $cartItem) {
                    if ($cartItem['product_id'] === $productId) {
                        $quantity = $cartItem['quantity'];
                        break;
                    }
                }

                if ($quantity > 0) {
                    $segmentPrice = SegmentPriceManager::getSegmentPrice($productId, $userId, $quantity);
                    $productPrice = wc_price($segmentPrice);

                    $this->enqueueQuantityUpdateScript($quantity, $productPrice);
                }
            }
        }
    }

    public function showProductOnCart(): void
    {
        global $woocommerce;

        if (!is_cart()) {
            return;
        }

        $quantity = 0;

        foreach ($woocommerce->cart->get_cart() as $cartItem) {
            $quantity += $cartItem['quantity'];
        }

        if ($quantity > 0) {
            $this->enqueueQuantityUpdateScript($quantity, 0);
        }
    }

    public function enqueueQuantityUpdateScript(int $quantity, $price): void
    {
        $jsUrl = plugins_url('storekeeper-for-woocommerce/resources/js/frontend/price.js');

        wp_enqueue_script('quantity-update-script', $jsUrl, ['jquery'], null, true);
        wp_localize_script('quantity-update-script', 'productData', [
            'quantity' => $quantity,
            'price' => $price,
        ]);
    }
}
