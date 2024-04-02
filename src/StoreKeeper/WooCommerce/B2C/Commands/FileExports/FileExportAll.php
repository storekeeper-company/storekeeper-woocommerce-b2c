<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\AllFileExport;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;

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
        $synopsis = parent::getSynopsis();
        $synopsis[] = [
            'type' => 'flag',
            'name' => 'export-not-active-products',
            'description' => __('Include products that are not published on export.', I18N::DOMAIN),
            'optional' => true,
        ];

        $synopsis[] = [
            'type' => 'flag',
            'name' => 'skip-empty-tags',
            'description' => __('Skip empty tags (tags with no products attached)', I18N::DOMAIN),
            'optional' => true,
        ];

        return $synopsis;
    }

    /* @var null|AllFileExport $allFileExport */
    protected $allFileExport;

    public function execute(array $arguments, array $assoc_arguments)
    {
        if (array_key_exists('export-not-active-products', $assoc_arguments)) {
            $this->getNewFileExportInstance()->setShouldExportActiveProductsOnly(false);
        }

        if (array_key_exists('skip-empty-tags', $assoc_arguments)) {
            $this->getNewFileExportInstance()->setShouldSkipEmptyTag(false);
        }

        parent::execute($arguments, $assoc_arguments);
    }

    /**
     * @return AllFileExport
     */
    public function getNewFileExportInstance(): IFileExport
    {
        if (is_null($this->allFileExport)) {
            $this->allFileExport = new AllFileExport(new WpCLILogger());
        }

        return $this->allFileExport;
    }
}
