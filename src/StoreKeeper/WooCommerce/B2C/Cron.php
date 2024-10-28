<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Models\PaymentModel;

class Cron
{
    public function syncAllPaidOrders(): void
    {
        if (class_exists('WC_Order_Query')) {
            $query = new WC_Order_Query([
                'status' => ['processing', 'completed'],
                'limit' => -1,
            ]);

            $orders = $query->get_orders();
            foreach ($orders as $order) {
                $orderId = $order->get_id();
                $noPaidOrder = PaymentModel::where('order_id', $orderId)
                    ->where('is_paid', 0)
                    ->exists();

                if ($noPaidOrder) {
                    try {
                        $export = new Tools\OrderHandler();
                        $export->create($orderId);
                    } catch (\Exception $e) {
                        error_log('Failed to sync order ID '.$order->get_id().': '.$e->getMessage());
                    }
                }
            }
        } else {
            error_log('WC_Order_Query not found.');
        }
    }
}
