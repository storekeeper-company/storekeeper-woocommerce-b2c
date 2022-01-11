<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\TagImport;

class SyncWoocommerceTags extends AbstractSyncCommand
{
    public static function getShortDescription(): string
    {
        return __('Sync all categories.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Sync all categories from Storekeeper Backoffice', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return []; // No synopsis
    }

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
