<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use StoreKeeper\WooCommerce\B2C\Exceptions\FileExportFailedException;
use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\Interfaces\ProductExportInterface;
use StoreKeeper\WooCommerce\B2C\Interfaces\TagExportInterface;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

class AllFileExport extends AbstractFileExport implements ProductExportInterface, TagExportInterface
{
    public const EXPORTS = [
        FileExportTypeHelper::ATTRIBUTE,
        FileExportTypeHelper::ATTRIBUTE_OPTION,
        FileExportTypeHelper::CATEGORY,
        FileExportTypeHelper::TAG,
        FileExportTypeHelper::CUSTOMER,
        FileExportTypeHelper::PRODUCT,
        FileExportTypeHelper::PRODUCT_BLUEPRINT,
    ];

    private $shouldExportActiveProductsOnly = true;
    private $shouldSkipEmptyTag = false;

    public function setShouldExportActiveProductsOnly(bool $shouldExportActiveProductsOnly): void
    {
        $this->shouldExportActiveProductsOnly = $shouldExportActiveProductsOnly;
    }

    public function setShouldSkipEmptyTag(bool $shouldSkipEmptyTag): void
    {
        $this->shouldSkipEmptyTag = $shouldSkipEmptyTag;
    }

    public function getType(): string
    {
        return FileExportTypeHelper::ALL;
    }

    /**
     * @throws FileExportFailedException
     */
    public function runExport(?string $exportLanguage = null): string
    {
        $exportLanguage = $exportLanguage ?? Language::getSiteLanguageIso2();
        $exports = [];

        foreach (self::EXPORTS as $index => $exportType) {
            $exports[] = $this->executeRunExport($exportType, $exportLanguage);

            $exportName = FileExportTypeHelper::getTypePluralName($exportType);
            $this->reportUpdate(count(self::EXPORTS), $index, "Processed $exportName");
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($this->filePath, \ZipArchive::CREATE)) {
            throw new FileExportFailedException('Unable to write to '.$this->filePath);
        }

        foreach ($exports as $i => $export) {
            $zip->addFile($export, 'step_'.($i + 1).'_'.basename($export));
        }

        if (true !== $zip->close()) {
            throw new FileExportFailedException('Unable to close '.$this->filePath);
        }

        foreach ($exports as $export) {
            unlink($export);
        }

        return $this->filePath;
    }

    /**
     * @throws \Exception
     */
    private function executeRunExport(string $exportType, string $exportLanguage): string
    {
        $exportClass = FileExportTypeHelper::getClass($exportType);
        $export = new $exportClass();

        if ($export instanceof ProductExportInterface) {
            $export->setShouldExportActiveProductsOnly($this->shouldExportActiveProductsOnly);
        }

        if ($export instanceof TagExportInterface) {
            $export->setShouldSkipEmptyTag($this->shouldSkipEmptyTag);
        }

        return $export->runExport($exportLanguage);
    }
}
