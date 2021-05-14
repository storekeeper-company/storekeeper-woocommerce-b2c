<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\Commands\AbstractCommand;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExportCommand;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

abstract class AbstractFileExportCommand extends AbstractCommand implements IFileExportCommand
{
    public function execute(array $arguments, array $assoc_arguments)
    {
        $lang = Language::getSiteLanguageIso2();
        if (array_key_exists('lang', $assoc_arguments)) {
            $lang = $assoc_arguments['lang'];
        }

        $export = $this->getNewFileExportInstance();
        $export->runExport($lang);
        $exportedFileUrl = $export->getDownloadUrl();
        \WP_CLI::success("Export successful, download the exported file here: $exportedFileUrl");
    }
}
