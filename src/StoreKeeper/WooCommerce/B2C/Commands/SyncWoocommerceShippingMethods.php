<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Backoffice\BackofficeCore;
use StoreKeeper\WooCommerce\B2C\Exceptions\ShippingMethodImportException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\ShippingMethodImport;

class SyncWoocommerceShippingMethods extends AbstractSyncCommand
{
    public function execute(array $arguments, array $assoc_arguments)
    {
        if (!BackofficeCore::isShippingMethodUsed()) {
            throw new ShippingMethodImportException(__('Shipping method synchronization is not enabled.', I18N::DOMAIN));
        }
        if ($this->prepareExecute()) {
            $import = new ShippingMethodImport();
            $import->setLogger($this->logger);
            $import->run();
        }
    }

    public static function getShortDescription(): string
    {
        return __('Sync all shipping methods.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Sync all shipping methods from Storekeeper Backoffice to be used during checkout.', I18N::DOMAIN);
    }

    public static function getSynopsis()
    {
        return [];
    }
}
