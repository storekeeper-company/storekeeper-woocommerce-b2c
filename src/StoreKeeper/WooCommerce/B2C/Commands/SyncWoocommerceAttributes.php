<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\AttributeImport;

class SyncWoocommerceAttributes extends AbstractSyncCommand
{
    public static function getShortDescription(): string
    {
        return __('Sync all product attributes.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Sync all product attributes from Storekeeper Backoffice', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return []; // No synopsis
    }

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
