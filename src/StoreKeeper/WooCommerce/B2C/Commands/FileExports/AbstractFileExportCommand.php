<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\Commands\AbstractCommand;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExportCommand;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

abstract class AbstractFileExportCommand extends AbstractCommand implements IFileExportCommand
{
    public const LANGUAGE_OPTIONS = [
        'en',
        'nl',
        'de',
    ];

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
