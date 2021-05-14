<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\ProductBlueprintFileExport;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;

class FileExportProductBlueprint extends AbstractFileExportCommand
{
    public function getNewFileExportInstance(): IFileExport
    {
        return new ProductBlueprintFileExport(new WpCLILogger());
    }
}
