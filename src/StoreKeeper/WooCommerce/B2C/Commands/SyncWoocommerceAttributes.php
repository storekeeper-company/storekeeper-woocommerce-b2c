<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Imports\AttributeImport;

class SyncWoocommerceAttributes extends AbstractSyncCommand
{
    /**
     * Sync all product attributes.
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            $import = new AttributeImport();
            $import->setLogger($this->logger);
            $import->run();
        }
    }
}
