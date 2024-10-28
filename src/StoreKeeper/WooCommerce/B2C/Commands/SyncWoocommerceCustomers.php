<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\CustomerImport;

class SyncWoocommerceCustomers extends AbstractSyncCommand
{
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            $import = new CustomerImport();
            $import->setLogger($this->logger);
            $import->run();
        }
    }

    public static function getShortDescription(): string
    {
        return __('Sync all customers.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Sync all customers from Storekeeper Backoffice.', I18N::DOMAIN);
    }

    public static function getSynopsis()
    {
        return [];
    }
}
