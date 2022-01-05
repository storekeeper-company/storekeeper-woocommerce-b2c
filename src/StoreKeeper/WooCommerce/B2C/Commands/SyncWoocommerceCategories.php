<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\CategoryImport;

class SyncWoocommerceCategories extends AbstractSyncCommand
{
    public static function getShortDescription(): string
    {
        return __('Sync all tags.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Sync all tags from Storekeeper Backoffice', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return []; // No synopsis
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            $import = new CategoryImport($assoc_arguments);
            $import->setLogger($this->logger);
            $import->run();
        }
    }
}
