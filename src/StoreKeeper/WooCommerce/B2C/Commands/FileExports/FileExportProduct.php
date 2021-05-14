<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\ProductFileExport;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;

class FileExportProduct extends AbstractFileExportCommand
{
    public function getNewFileExportInstance(): IFileExport
    {
        return new ProductFileExport(new WpCLILogger());
    }
}
