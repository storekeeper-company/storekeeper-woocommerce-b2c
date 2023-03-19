<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\ProductUpdateImport;

class ProductUpdateImportTask extends AbstractTask
{
    public function run(array $task_options = []): void
    {
        if (
            $this->taskMetaExists('storekeeper_id') &&
            $this->taskMetaExists('scope')
        ) {
            $storekeeperId = $this->getTaskMeta('storekeeper_id');
            $scope = $this->getTaskMeta('scope');
            $product = new ProductUpdateImport(
                [
                    'storekeeper_id' => $storekeeperId,
                    'scope' => $scope,
                    'debug' => key_exists('debug', $task_options) ? $task_options['debug'] : false,
                ]
            );
            $product->setTaskHandler($this->getTaskHandler());
            $product->run();
        }
    }
}
