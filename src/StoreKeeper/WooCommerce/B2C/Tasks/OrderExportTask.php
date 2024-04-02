<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;

class OrderExportTask extends AbstractTask
{
    /**
     * @throws \Exception|\Throwable
     */
    public function run(array $task_options = []): void
    {
        if ($this->taskMetaExists('woocommerce_id')) {
            $prod = new OrderExport(
                [
                    'id' => $this->getTaskMeta('woocommerce_id'),
                    'debug' => key_exists('debug', $task_options) ? $task_options['debug'] : false,
                ]
            );

            $prod->run();
        }
    }
}
