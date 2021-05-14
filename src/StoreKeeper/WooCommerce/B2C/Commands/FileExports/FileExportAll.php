<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\AllFileExport;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;

class FileExportAll extends AbstractFileExportCommand
{
    public function getNewFileExportInstance(): IFileExport
    {
        return new AllFileExport(new WpCLILogger());
    }
}
