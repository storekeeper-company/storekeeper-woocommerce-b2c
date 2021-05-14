<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;

class OrderExportTask extends AbstractTask
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
        if ($this->taskMetaExists('woocommerce_id')) {
            $prod = new OrderExport(
                [
                    'id' => $this->getTaskMeta('woocommerce_id'),
                    'debug' => key_exists('debug', $task_options) ? $task_options['debug'] : false,
                ]
            );

            return $this->throwExceptionArray($prod->run());
        }

        return true;
    }
}
