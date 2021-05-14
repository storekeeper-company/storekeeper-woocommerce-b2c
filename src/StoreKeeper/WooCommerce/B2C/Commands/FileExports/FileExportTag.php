<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\TagFileExport;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;

class FileExportTag extends AbstractFileExportCommand
{
    public function getNewFileExportInstance(): IFileExport
    {
        return new TagFileExport(new WpCLILogger());
    }
}
