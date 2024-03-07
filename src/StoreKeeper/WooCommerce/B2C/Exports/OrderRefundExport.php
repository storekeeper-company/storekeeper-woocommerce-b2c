<?php

namespace StoreKeeper\WooCommerce\B2C\Exports;

class OrderRefundExport extends OrderExport
{
    protected function getFunctionMultiple()
    {
        return null;
    }

    protected function getArguments()
    {
        return null;
    }

    /**
     * @param \WC_Order $order
     *
     * @throws \Exception
     */
    protected function processItem($order): void
    {
        $orderId = $order->get_id();
        $this->debug('Exporting order refund for order with id '.$orderId);

        $this->processRefunds($orderId, $this->get_storekeeper_id());
    }
}
