<?php

namespace StoreKeeper\WooCommerce\B2C\Interfaces;

interface IFileExportCommand
{
    public function getNewFileExportInstance(): IFileExport;
}
