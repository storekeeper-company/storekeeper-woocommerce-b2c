<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\CustomerFileExport;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;

class FileExportCustomer extends AbstractFileExportCommand
{
    public function getNewFileExportInstance(): IFileExport
    {
        return new CustomerFileExport(new WpCLILogger());
    }
}
