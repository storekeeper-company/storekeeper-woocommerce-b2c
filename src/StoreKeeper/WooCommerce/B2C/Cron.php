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
    public function scheduleMonitorPaymentStatusCron(): void
    {
        $args = [
            'status' => 'pending',
            'limit'  => -1,
        ];
        $orders = wc_get_orders($args);

        foreach ($orders as $order) {
            $orderStatus = $order->get_status();
            error_log('Order ID: ' . $order->get_id() . ' - Order Status: ' . $orderStatus);

            if ($orderStatus === 'pending') {
                error_log('Cancelling Order ID: ' . $order->get_id() . ' due to failed payment.');
                $order->update_status('cancelled', 'Payment failed, order cancelled.');
            }
        }
    }
}
