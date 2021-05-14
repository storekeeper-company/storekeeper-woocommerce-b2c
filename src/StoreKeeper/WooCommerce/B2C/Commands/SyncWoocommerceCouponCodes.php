<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Imports\CouponCodeImport;

class SyncWoocommerceCouponCodes extends AbstractSyncCommand
{
    /**
     * Sync coupon codes.
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            $import = new CouponCodeImport();
            $import->setLogger($this->logger);
            $import->run();
        }
    }
}
