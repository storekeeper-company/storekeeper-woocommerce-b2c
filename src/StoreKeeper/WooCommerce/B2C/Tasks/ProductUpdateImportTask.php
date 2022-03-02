<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\ProductUpdateImport;

class ProductUpdateImportTask extends AbstractTask
{
    public function run($task_options = [])
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');
            $exceptions = [];
            $product = new ProductUpdateImport(
                [
                    'storekeeper_id' => $storekeeper_id,
                    'debug' => key_exists('debug', $task_options) ? $task_options['debug'] : false,
                ]
            );
            $product->setTaskHandler($this->getTaskHandler());
            $exceptions = array_merge($exceptions, $product->run());

            return $this->throwExceptionArray($exceptions);
        }

        return true;
    }
}
