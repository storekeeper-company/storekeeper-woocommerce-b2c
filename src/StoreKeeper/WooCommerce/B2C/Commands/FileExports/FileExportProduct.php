<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\ProductFileExport;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\IFileExport;
use StoreKeeper\WooCommerce\B2C\Loggers\WpCLILogger;

class FileExportProduct extends AbstractFileExportCommand
{
    public static function getShortDescription(): string
    {
        return __('Export CSV file for products.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Generate and export CSV files for products which will be used to import to Storekeeper Backoffice.', I18N::DOMAIN);
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

        return $synopsis;
    }

    /* @var null|ProductFileExport $productFileExport */
    protected $productFileExport;

    public function execute(array $arguments, array $assoc_arguments)
    {
        if (array_key_exists('export-not-active-products', $assoc_arguments)) {
            $this->getNewFileExportInstance()->setShouldExportActiveProductsOnly(false);
        }
        parent::execute($arguments, $assoc_arguments);
    }

    /**
     * @return ProductFileExport
     */
    public function getNewFileExportInstance(): IFileExport
    {
        if (is_null($this->productFileExport)) {
            $this->productFileExport = new ProductFileExport(new WpCLILogger());
        }

        return $this->productFileExport;
    }
}
