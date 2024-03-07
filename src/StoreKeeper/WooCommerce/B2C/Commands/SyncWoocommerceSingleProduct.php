<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class SyncWoocommerceSingleProduct extends SyncWoocommerceProductPage
{
    /**
     * @return mixed|void
     *
     * @throws \Exception
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
     * @throws \Exception
     */
    public function runSync($assoc_arguments): void
    {
        $import = new ProductImport($assoc_arguments);
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
