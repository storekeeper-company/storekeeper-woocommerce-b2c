<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\AttributeOptionsFileExport;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;

class FileExportAttributeOption extends AbstractFileExportCommand
{
    public function getNewFileExportInstance(): IFileExport
    {
        return new AttributeOptionsFileExport(new WpCLILogger());
    }
}
