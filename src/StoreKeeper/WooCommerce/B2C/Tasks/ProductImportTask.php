<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;

class ProductImportTask extends AbstractTask
{
    public function run($task_options = [])
    {
        throw new \Exception('boom'); // todo remove me

        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');
            $exceptions = [];
            $product = new ProductImport(
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
