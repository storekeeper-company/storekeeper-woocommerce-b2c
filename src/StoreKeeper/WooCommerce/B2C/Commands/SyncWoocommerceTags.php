<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Imports\TagImport;

class SyncWoocommerceTags extends AbstractSyncCommand
{
    /**
     * Sync tags (known as Product labels in backoffice).
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            $import = new TagImport();
            $import->setLogger($this->logger);
            $import->run();
        }
    }
}
