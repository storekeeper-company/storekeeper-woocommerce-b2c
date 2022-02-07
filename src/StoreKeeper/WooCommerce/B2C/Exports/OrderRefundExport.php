<?php

namespace StoreKeeper\WooCommerce\B2C\Exports;

use Exception;
use WC_Order;

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
     * @param WC_Order $WpObject
     *
     * @return bool|mixed
     *
     * @throws Exception
     */
    protected function processItem($WpObject)
    {
        $orderId = $WpObject->get_id();
        $this->debug('Exporting order refund for order with id '.$orderId);

        $this->processRefunds($orderId, $this->get_storekeeper_id());

        return true;
    }
}
