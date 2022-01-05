<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\AllFileExport;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

class FileExportAll extends AbstractFileExportCommand
{
    public static function getShortDescription(): string
    {
        return __('Export CSV files for Storekeeper WooCommerce related entities/objects.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Generate and export CSV files for customers, tags, categories, attributes, attribute options, product blueprints, and products in a zip file which will be used to import to Storekeeper Backoffice.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'assoc',
                'name' => 'lang',
                'description' => __('The language to which the entities will be exported.', I18N::DOMAIN),
                'optional' => true,
                'default' => Language::getSiteLanguageIso2(),
                'options' => self::LANGUAGE_OPTIONS,
            ],
        ];
    }

    public function getNewFileExportInstance(): IFileExport
    {
        return new AllFileExport(new WpCLILogger());
    }
}
