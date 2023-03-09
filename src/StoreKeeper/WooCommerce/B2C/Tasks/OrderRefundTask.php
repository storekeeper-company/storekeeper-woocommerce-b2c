<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Exports\OrderRefundExport;

class OrderRefundTask extends AbstractTask
{
    /**
     * @throws \Exception
     */
    public function run(array $task_options = []): void
    {
        if ($this->taskMetaExists('woocommerce_order_id')) {
            $refundExport = new OrderRefundExport(
                [
                    'id' => $this->getTaskMeta('woocommerce_order_id'),
                    'debug' => key_exists('debug', $task_options) ? $task_options['debug'] : false,
                ]
            );

            $refundExport->run();
        }
    }
}
