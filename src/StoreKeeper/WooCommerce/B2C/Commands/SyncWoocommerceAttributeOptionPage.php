<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Imports\AttributeOptionImport;

class SyncWoocommerceAttributeOptionPage extends AbstractSyncCommand
{
    /**
     * Sync all product attribute options (should be done after attributes are there).
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            if (key_exists('limit', $assoc_arguments) && key_exists('start', $assoc_arguments)) {
                $this->runWithPagination($assoc_arguments);
            } else {
                throw new BaseException('Limit and start attribute need to be set');
            }
        }
    }

    public function runWithPagination($assoc_arguments)
    {
        $import = new AttributeOptionImport($assoc_arguments);
        $import->setLogger($this->logger);
        $import->run();
    }
}
