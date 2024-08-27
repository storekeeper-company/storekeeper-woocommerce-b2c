<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use WC_Order;

class OrderHandler
{
    public const SHOP_PRODUCT_ID_MAP = 'shop_product_id_map';
    public const TO_BE_SYNCHRONIZED_META_KEY = 'to_be_synchronized';

    /**
     * @hook $this->loader->add_action('woocommerce_checkout_order_processed', $orderHandler, 'create', self::HIGH_PRIORITY);
     *
     * @throws \Exception
     */
    public function create($order_id): ?array
    {
        if (!$this->isSyncAllowed($order_id)) {
            return null;
        }

        return TaskHandler::scheduleTask(
            TaskHandler::ORDERS_EXPORT,
            $order_id,
            [
                'woocommerce_id' => $order_id,
            ]
        );
    }

    public function addToBeSynchronizedMetadata(int $orderId, \WC_Order $order): void
    {
        if ($this->isSyncAllowed($orderId)) {
            $order->add_meta_data(self::TO_BE_SYNCHRONIZED_META_KEY, 'yes');
            $order->save();
        }
    }

    /**
     * Szymon:
     * this function most probably exists to not call update the order is imported from backend
     * It should removed as soon as unit test are there, cos it's F#@$%^&NG cancer.
     **/
    public function updateWithIgnore($order_id): ?array
    {
        global $storekeeper_ignore_order_id;

        if ($storekeeper_ignore_order_id !== $order_id && $this->isSyncAllowed($order_id)) {
            return TaskHandler::scheduleTask(
                TaskHandler::ORDERS_EXPORT,
                $order_id,
                [
                    'woocommerce_id' => $order_id,
                ],
                // Force update is needed to make sure everything is being synced,
                // We had an issue where an order was created when the sync started,
                // and the paid status was never synced over
                true
            );
        }

        return null;
    }

    /**
     * @param $order WC_Order
     */
    public function addShopProductIdsToMetaData($order, $data)
    {
        $id_map = [];

        /**
         * @var $orderProduct \WC_Order_Item_Product
         */
        foreach ($order->get_items() as $index => $orderProduct) {
            $var_prod_id = $orderProduct->get_variation_id();
            $is_variation = $var_prod_id > 0; // Variation_id is 0 my default, if it is any other, its a variation products;

            if ($is_variation) {
                $post_id = $var_prod_id;
            } else {
                $post_id = $orderProduct->get_product_id();
            }

            $id_map[$post_id] = (int) get_post_meta($post_id, 'storekeeper_id', true);
        }

        $order->add_meta_data(self::SHOP_PRODUCT_ID_MAP, $id_map);
    }

    protected function isSyncAllowed(int $orderId): bool
    {
        if (StoreKeeperOptions::isOrderSyncEnabled()) {
            $order = new \WC_Order($orderId);
            $orderCreatedDate = $order->get_date_created();

            $orderStatus = $order->get_status();

            if ('checkout-draft' === $orderStatus) {
                // @see https://woocommerce.com/document/cart-checkout-blocks-status/
                // When using WooCommerce Blocks for the checkout process, orders are created when the shopper arrives to the checkout page
                // The pending payment status does not accurately reflect the state of these orders, which may be incomplete or unsubmitted.
                // To accommodate for this, the checkout-draft status is used until the order is submitted.
                return false;
            }

            if (is_null($orderCreatedDate)) {
                return false;
            }

            $orderCreatedDateUTC = clone $orderCreatedDate;
            $orderCreatedDateUTC->setTimezone(new \DateTimeZone('UTC'));

            if (is_null(StoreKeeperOptions::get(StoreKeeperOptions::ORDER_SYNC_FROM_DATE))) {
                return true;
            }

            $orderSyncFromDate = DatabaseConnection::formatFromDatabaseDateIfNotEmpty(
                StoreKeeperOptions::get(StoreKeeperOptions::ORDER_SYNC_FROM_DATE)
            );

            if (
                $orderSyncFromDate
                && strtotime($orderSyncFromDate->format('Y-m-d')) <= strtotime($orderCreatedDateUTC->format('Y-m-d'))
            ) {
                return true;
            }
        }

        return false;
    }
}
