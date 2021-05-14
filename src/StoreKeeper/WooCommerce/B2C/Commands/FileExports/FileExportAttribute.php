<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\AttributeFileExport;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;

class FileExportAttribute extends AbstractFileExportCommand
{
    public function getNewFileExportInstance(): IFileExport
    {
        return new AttributeFileExport(new WpCLILogger());
    }
}
