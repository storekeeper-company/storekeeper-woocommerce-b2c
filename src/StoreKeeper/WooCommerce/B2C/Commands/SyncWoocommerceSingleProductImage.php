<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use Exception;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImageImport;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class SyncWoocommerceSingleProductImage extends SyncWoocommerceProductPage
{
    /**
     * @return mixed|void
     *
     * @throws Exception
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            if (array_key_exists('storekeeper_id', $assoc_arguments)) {
                $this->runSync($assoc_arguments);
            } else {
                throw new BaseException('No product id provided');
            }
        }
    }

    /**
     * @throws Exception
     */
    public function runSync($assoc_arguments): void
    {
        $import = new ProductImageImport($assoc_arguments);
        $import->setLogger($this->logger);
        $import->setSyncProductVariations(true);
        $import->setTaskHandler(new TaskHandler());
        $import->run(
            [
                'skip_cross_sell' => true,
                'skip_upsell' => true,
            ]
        );
    }
}
