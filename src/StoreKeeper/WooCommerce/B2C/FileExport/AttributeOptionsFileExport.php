<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

class AttributeOptionsFileExport extends AbstractCSVFileExport
{
    public function getType(): string
    {
        return FileExportTypeHelper::ATTRIBUTE_OPTION;
    }

    public function getPaths(): array
    {
        return [
            'name' => 'Name',
            'label' => 'Label',
            'translatable.lang' => 'Language',
            'is_main_lang' => 'Is main language',
            'is_default' => 'Is default',
            'image_url' => 'Image URL',
            'attribute.name' => 'Attribute name',
            'attribute.label' => 'Attribute label',
            'date_created' => 'Date created',
            'date_updated' => 'Date updated',
        ];
    }

    /**
     * Runs the export, once done it returns the path to the exported file.
     */
    public function runExport(?string $exportLanguage = null): string
    {
        $exportLanguage = $exportLanguage ?? Language::getSiteLanguageIso2();
        $options = AttributeExport::getAttributeOptions();

        foreach ($options as $option) {
            $lineData = [];
            $lineData['name'] = $option['name'];
            $lineData['label'] = $option['label'];
            $lineData['translatable.lang'] = $exportLanguage;
            $lineData['attribute.name'] = $option['attribute_name'];
            $lineData['attribute.label'] = $option['attribute_label'];

            $this->writeLineData($lineData);
        }

        return $this->filePath;
    }
}
