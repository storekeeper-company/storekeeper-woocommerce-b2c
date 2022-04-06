<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\ProductStockImport;

class ProductStockUpdateTask extends AbstractTask
{
    public function run($task_options = [])
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');
            $exceptions = [];
            $productStock = new ProductStockImport(
                [
                    'storekeeper_id' => $storekeeper_id,
                    'debug' => key_exists('debug', $task_options) ? $task_options['debug'] : false,
                ]
            );
            $productStock->setTaskHandler($this->getTaskHandler());
            $exceptions = array_merge($exceptions, $productStock->run());

            $this->throwExceptionArray($exceptions);
        }

        return true;
    }
}
