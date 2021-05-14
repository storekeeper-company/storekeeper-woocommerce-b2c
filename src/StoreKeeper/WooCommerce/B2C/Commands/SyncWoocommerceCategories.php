<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Imports\CategoryImport;

class SyncWoocommerceCategories extends AbstractSyncCommand
{
    /**
     * Sync all categories.
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            $import = new CategoryImport($assoc_arguments);
            $import->setLogger($this->logger);
            $import->run();
        }
    }
}
