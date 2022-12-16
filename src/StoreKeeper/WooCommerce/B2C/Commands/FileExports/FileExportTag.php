<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\TagFileExport;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

class FileExportTag extends AbstractFileExportCommand
{
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
            [
                'type' => 'flag',
                'name' => 'skip-empty',
                'description' => __('Skip empty tags (tags with no products attached)', I18N::DOMAIN),
                'optional' => true,
            ],
        ];
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        $lang = Language::getSiteLanguageIso2();
        if (array_key_exists('lang', $assoc_arguments)) {
            $lang = $assoc_arguments['lang'];
        }

        $skipEmpty = array_key_exists('skip-empty', $assoc_arguments);

        $export = new TagFileExport(new WpCLILogger());
        $export->setShouldSkipEmptyTag($skipEmpty);
        $export->runExport($lang);
        $exportedFileUrl = $export->getDownloadUrl();
        \WP_CLI::success("Export successful, download the exported file here: $exportedFileUrl");
    }

    public static function getShortDescription(): string
    {
        return __('Export CSV file for tags.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Generate and export CSV files for tags which will be used to import to Storekeeper Backoffice.', I18N::DOMAIN);
    }

    public function getNewFileExportInstance(): IFileExport
    {
        return new TagFileExport(new WpCLILogger());
    }
}
