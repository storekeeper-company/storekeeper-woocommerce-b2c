<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Imports\FeaturedAttributeImport;

class SyncWoocommerceFeaturedAttributes extends AbstractSyncCommand
{
    /**
     * Sync all featured product attribute options (should be done after attributes are there).
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            $import = new FeaturedAttributeImport();
            $import->setLogger($this->logger);
            $import->run();
        }
    }
}
