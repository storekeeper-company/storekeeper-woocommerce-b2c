<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\ProductBlueprintFileExport;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;

class FileExportProductBlueprint extends AbstractFileExportCommand
{
    public static function getShortDescription(): string
    {
        return __('Export CSV file for product blueprints.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Generate and export CSV files for product blueprints which will be used to import to Storekeeper Backoffice.', I18N::DOMAIN);
    }

    public function getNewFileExportInstance(): IFileExport
    {
        return new ProductBlueprintFileExport(new WpCLILogger());
    }
}
