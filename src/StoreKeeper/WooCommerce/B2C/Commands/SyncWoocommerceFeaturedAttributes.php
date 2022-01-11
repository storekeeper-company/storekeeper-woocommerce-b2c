<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\FeaturedAttributeImport;

class SyncWoocommerceFeaturedAttributes extends AbstractSyncCommand
{
    public static function getShortDescription(): string
    {
        return __('Sync all featured product attribute options.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Sync all featured product attribute options from Storekeeper Backoffice. Note that this should be executed when attributes are already synchronized.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return []; // No synopsis
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            $import = new FeaturedAttributeImport();
            $import->setLogger($this->logger);
            $import->run();
        }
    }
}
