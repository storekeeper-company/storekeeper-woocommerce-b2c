<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\AttributeOptionsFileExport;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

class AttributeOptionFileExportTest extends AbstractAttributeFileExportTest
{
    public function getFileExportClass(): string
    {
        return AttributeOptionsFileExport::class;
    }

    public function testAttributeOptionExportTest()
    {
        list($sizeAttributeData, $colourAttributeData, $qtyAttribute, $brandAttribute, $product) = $this->setupAttributes();

        $instance = $this->getNewFileExportInstance();
        $path = $instance->runExport(Language::getSiteLanguageIso2());
        $this->addFile($path);

        $spreadSheet = $this->readSpreadSheetFromPath($path);
        $mappedRows = $this->mapSpreadSheetRows(
            $spreadSheet,
            function ($row) {
                return $this->getMappedKey($row['attribute.name'], $row['name']);
            }
        );

        $otherKeys = [];
        foreach ($mappedRows as $key => $option) {
            list($attr, $opt) = explode('::', $key);
            if (!in_array($attr, [
                self::SA_COLOUR,
                self::SA_SIZE,
                self::SA_BRAND,
                self::SA_QTY,
            ])) {
                $otherKeys[] = $key;
            }
        }
        $this->assertEmpty($otherKeys, 'Other keys ware exported');
        $this->assertSystemAttributeOptions($mappedRows, $qtyAttribute['term_ids'], 'Box quanity');
        $this->assertSystemAttributeOptions($mappedRows, $sizeAttributeData['term_ids'], 'Size');
        $this->assertSystemAttributeOptions($mappedRows, $brandAttribute['term_ids'], 'Brand');
        $this->assertSystemAttributeOptions($mappedRows, $colourAttributeData['term_ids'], 'Colour');
    }

    private function assertSystemAttributeOptions(array $mappedRows, array $termIds, string $message)
    {
        foreach ($termIds as $termId) {
            $term = get_term($termId);
            $attributeMap = $this->getAttributeMap();
            $attribute = $attributeMap[$term->taxonomy];
            $mappedKey = $this->getMappedKeyForAttributeTerm($term);
            $this->assertArrayHasKey(
                $mappedKey,
                $mappedRows,
                'Keys available: '.implode(',', array_keys($mappedRows))
            );
            $mappedRow = $mappedRows[$mappedKey];

            $this->assertEquals(
                $term->name,
                $mappedRow['label'],
                "$message attribute option's label is incorrect"
            );

            $this->assertEquals(
                $term->slug,
                $mappedRow['name'],
                "$message attribute option's name is incorrect"
            );

            $this->assertEquals(
                Language::getSiteLanguageIso2(),
                $mappedRow['translatable.lang'],
                "$message attribute option's language is incorrect"
            );

            $this->assertEquals(
                AttributeExport::getAttributeKey($attribute->attribute_name, AttributeExport::TYPE_SYSTEM_ATTRIBUTE),
                $mappedRow['attribute.name'],
                "$message attribute option's name is incorrect"
            );

            $this->assertEquals(
                $attribute->attribute_label,
                $mappedRow['attribute.label'],
                "$message attribute option's name is incorrect"
            );
        }
    }

    private function assertSystemAttributeOptionsEmpty(array $mappedRows, array $termIds, string $type)
    {
        foreach ($termIds as $termId) {
            $term = get_term($termId);
            $mappedKey = $this->getMappedKeyForAttributeTerm($term);
            $this->assertArrayNotHasKey($mappedKey, $mappedRows, "$type options should not be exported");
        }
    }

    private $attributeMap;

    private function getAttributeMap(): array
    {
        if (!isset($this->attributeMap)) {
            $this->attributeMap = [];
            foreach (wc_get_attribute_taxonomies() as $attribute) {
                $this->attributeMap[$attribute->attribute_name] = $attribute;
                $this->attributeMap["pa_$attribute->attribute_name"] = $attribute;
            }
        }

        return $this->attributeMap;
    }

    protected function getMappedKeyForAttributeTerm($term): string
    {
        $attributeMap = $this->getAttributeMap();
        $attribute = $attributeMap[$term->taxonomy];
        $attributeName = AttributeExport::getAttributeKey(
            $attribute->attribute_name,
            $attribute->attribute_id ?
                AttributeExport::TYPE_SYSTEM_ATTRIBUTE :
                AttributeExport::TYPE_CUSTOM_ATTRIBUTE,
        );
        $mappedKey = $this->getMappedKey($attributeName, $term->slug);

        return $mappedKey;
    }

    protected function getMappedKey($attributeName, $name): string
    {
        $attributeName = AttributeExport::cleanAttributeTermPrefix($attributeName);

        return "{$attributeName}::{$name}";
    }
}
