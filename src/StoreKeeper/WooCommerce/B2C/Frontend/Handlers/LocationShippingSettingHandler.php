<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\I18N;

class LocationShippingSettingHandler implements WithHooksInterface
{
    /**
     * Register hooks
     *
     * @return void
     */
    public function registerHooks(): void
    {
        add_action('woocommerce_checkout_create_order', [$this, 'saveDeliveryDetailsToOrder']);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'displayDeliveryDetailsInAdmin']);
    }

    /**
     * Save delivery details in admin dashboard in WooCommerce
     * @param $order
     * @return void
     */
    public function saveDeliveryDetailsToOrder($order): void
    {
        $locationData = $_POST['storekeeper']['location'] ?? [];

        if (!empty($locationData)) {
            foreach ($locationData['shipping_method'] as $instanceId => $locationId) {
                $address = $locationData['address'][$locationId] ?? null;
                $timestamp = $locationData['date'][$locationId] ?? null;

                if ($address) {
                    $order->update_meta_data('_storekeeper_location_address', sanitize_text_field($address));
                    $order->update_meta_data('_storekeeper_location_id', sanitize_text_field($locationId));
                }

                if ($timestamp) {
                    $deliveryDate = (new \DateTime())->setTimestamp((int) $timestamp);
                    $order->update_meta_data('_storekeeper_delivery_date', $deliveryDate->format('Y-m-d H:i:s'));
                }
            }
        }
    }

    /**
     * Display delivery details in admin dashboard in WooCommerce
     * @param $order
     * @return void
     */
    public function displayDeliveryDetailsInAdmin($order): void
    {
        $address = $order->get_meta('_storekeeper_location_address');
        $deliveryDate = $order->get_meta('_storekeeper_delivery_date');

        if ($address || $deliveryDate) {
            echo '<p class="form-field form-field-wide"><strong>' . esc_html('Delivery Address', I18N::DOMAIN) . '</strong> ' . esc_html($address) . '</p>';
            echo '<p><strong>' . esc_html('Delivery Date', I18N::DOMAIN) . ':</strong> ' . esc_html($deliveryDate) . '</p>';
        }
    }
}
