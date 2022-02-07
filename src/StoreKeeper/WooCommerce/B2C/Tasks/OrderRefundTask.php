<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Exports\OrderRefundExport;

class OrderRefundTask extends AbstractTask
{
    /**
     * @param $task_options
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function run($task_options = [])
    {
        if ($this->taskMetaExists('woocommerce_order_id')) {
            $refundExport = new OrderRefundExport(
                [
                    'id' => $this->getTaskMeta('woocommerce_order_id'),
                    'debug' => key_exists('debug', $task_options) ? $task_options['debug'] : false,
                ]
            );

            return $this->throwExceptionArray($refundExport->run());
        }

        return true;
    }
}
