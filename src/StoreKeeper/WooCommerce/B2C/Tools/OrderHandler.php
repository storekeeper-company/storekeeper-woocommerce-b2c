<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use WC_Order;
use WP_Post;

class OrderHandler
{
    const SHOP_PRODUCT_ID_MAP = 'shop_product_id_map';
    const TO_BE_SYNCHRONIZED_META_KEY = 'to_be_synchronized';

    /**
     * @param $order_id
     *
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

    public function addMetadata(int $orderId, WC_Order $order): void
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
    public function updateWithIgnore($order_id): void
    {
        global $storekeeper_ignore_order_id;

        if (!$this->isSyncAllowed($order_id)) {
            return;
        }

        if ($storekeeper_ignore_order_id === $order_id) {
            return;
        }

        TaskHandler::scheduleTask(
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

    /* @deprecated  */
    public function delete($order_id): WP_Post
    {
        $meta_data = [];
        if (!empty(get_post_meta($order_id, 'storekeeper_id', true))) {
            $meta_data['storekeeper_id'] = get_post_meta($order_id, 'storekeeper_id', true);
        }

        return TaskHandler::scheduleTask(
            TaskHandler::ORDERS_DELETE,
            $order_id,
            [
                'woocommerce_id' => $order_id,
            ]
        );
    }

    /**
     * @param $order WC_Order
     * @param $data
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
            $order = new WC_Order($orderId);
            $orderCreatedDate = $order->get_date_created();

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
                $orderSyncFromDate &&
                strtotime($orderSyncFromDate->format('Y-m-d')) <= strtotime($orderCreatedDateUTC->format('Y-m-d'))
            ) {
                return true;
            }
        }

        return false;
    }
}
