<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\CategoryFileExport;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;

class FileExportCategory extends AbstractFileExportCommand
{
    public function getNewFileExportInstance(): IFileExport
    {
        return new CategoryFileExport(new WpCLILogger());
    }
}
