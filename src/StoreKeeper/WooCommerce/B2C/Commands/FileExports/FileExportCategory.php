<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\CategoryFileExport;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;

class FileExportCategory extends AbstractFileExportCommand
{
    public static function getShortDescription(): string
    {
        return __('Export CSV file for product categories.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Generate and export CSV files for product categories which will be used to import to Storekeeper Backoffice.', I18N::DOMAIN);
    }

    public function getNewFileExportInstance(): IFileExport
    {
        return new CategoryFileExport(new WpCLILogger());
    }
}
