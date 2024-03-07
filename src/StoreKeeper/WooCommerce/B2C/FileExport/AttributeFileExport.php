<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\Tools\Base36Coder;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

class AttributeFileExport extends AbstractCSVFileExport
{
    public const ATTRIBUTE_SET_DEFAULT_ALIAS = 'default';

    public function getType(): string
    {
        return FileExportTypeHelper::ATTRIBUTE;
    }

    public function getPaths(): array
    {
        return [
            'name' => 'Name',
            'label' => 'Label',
            'translatable.lang' => 'Language',
            'is_main_lang' => 'Is main language',
            'is_options' => 'Has options',
            'type' => 'Options type',
            'required' => 'Required',
            'published' => 'Published',
            'unique' => 'Unique',
            self::getAttributeSetPath() => 'Default',
        ];
    }

    /**
     * Runs the export, once done it returns the path to the exported file.
     */
    public function runExport(?string $exportLanguage = null): string
    {
        $exportLanguage = $exportLanguage ?? Language::getSiteLanguageIso2();
        $attributes = AttributeExport::getAllNonFeaturedAttributes();

        $map = $this->keyValueMapArray(
            $attributes,
            function ($item) {
                return $item['name'];
            }
        );

        $total = count($map);
        $index = 0;
        foreach ($map as $itemName => $item) {
            $lineData = [];
            $lineData['name'] = $itemName;
            $lineData['label'] = $item['label'];
            $lineData['translatable.lang'] = $exportLanguage;
            $lineData['is_options'] = $item['options'];
            $lineData['type'] = 'string';
            $lineData['published'] = true;
            $lineData[self::getAttributeSetPath()] = true;

            $this->writeLineData($lineData);

            ++$index;
            if (0 === $index % 10) {
                $this->reportUpdate($total, $index, 'Exported 10 attributes');
            }
        }

        return $this->filePath;
    }

    private static function getAttributeSetPath($name = self::ATTRIBUTE_SET_DEFAULT_ALIAS): string
    {
        $encoded = Base36Coder::encode($name);

        return "attribute_set.encoded__$encoded.is_assigned";
    }
}
