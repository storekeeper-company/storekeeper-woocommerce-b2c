<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\AttributeFileExport;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use WC_Product_Attribute;

class AttributeFileExportTest extends AbstractAttributeFileExportTest
{
    public function getFileExportClass(): string
    {
        return AttributeFileExport::class;
    }

    public function testAttributeExportTest()
    {
        list($sizeAttributeData, $colourAttributeData, $qtyAttribute, $brandAttribute, $product) = $this->setupAttributes();

        $instance = $this->getNewFileExportInstance();
        $path = $instance->runExport(Language::getSiteLanguageIso2());
        $this->addFile($path);

        $spreadSheet = $this->readSpreadSheetFromPath($path);
        $mappedRows = $this->mapSpreadSheetRows(
            $spreadSheet,
            function ($row) {
                return $row['name'];
            }
        );

        $exported_keys = array_keys($mappedRows);
        sort($exported_keys);
        $this->assertEquals(
            [
                self::CA_CUSTOM_TITLE_MULTIPLE,
                self::CA_CUSTOM_TITLE_ONE,
                self::SA_COLOUR,
                self::SA_SIZE,
                // qty and brand are featured
            ],
            $exported_keys,
            'Correct keys are exported'
        );

        $this->assertSystemAttribute($colourAttributeData, $mappedRows[self::SA_COLOUR], 'Colour');
        $this->assertSystemAttribute($sizeAttributeData, $mappedRows[self::SA_SIZE], 'Size');
        $product_attributes = $product->get_attributes();
        $this->assertProductAttribute(
            $product_attributes['custom-title-one'],
            $mappedRows[self::CA_CUSTOM_TITLE_ONE],
            'Single option'
        );
        $this->assertProductAttribute(
            $product_attributes['custom-title-multiple'],
            $mappedRows[self::CA_CUSTOM_TITLE_MULTIPLE],
            'Multiple options'
        );
    }

    private function assertSystemAttribute(array $attributeData, array $dataRow, string $type)
    {
        $attribute = wc_get_attribute($attributeData['attribute_id']);
        $this->assertEquals(
            $attribute->name,
            $dataRow['label'],
            "$type attribute label is not correctly exported"
        );

        $this->assertEquals(
            AttributeExport::getAttributeKey($attribute->slug, AttributeExport::TYPE_SYSTEM_ATTRIBUTE),
            $dataRow['name'],
            "$type attribute name is not correctly exported"
        );

        $this->assertEquals(
            Language::getSiteLanguageIso2(),
            $dataRow['translatable.lang'],
            "$type attribute language is not correctly exported"
        );

        $this->assertEquals(
            'string',
            $dataRow['type'],
            "$type attribute type is not correctly exported"
        );

        $this->assertEquals(
            'yes',
            $dataRow['published'],
            "$type attribute published is not correctly exported"
        );

        $this->assertEquals(
            'yes',
            $dataRow['is_options'],
            "$type attribute is_options is not correctly exported"
        );
    }

    private function assertProductAttribute(WC_Product_Attribute $attribute, array $dataRow, string $type)
    {
        $this->assertEquals(
            $attribute->get_name(),
            $dataRow['label'],
            "$type attribute label is not correctly exported"
        );

        $this->assertEquals(
            AttributeExport::getProductAttributeKey($attribute),
            $dataRow['name'],
            "$type attribute name is not correctly exported"
        );

        $this->assertEquals(
            Language::getSiteLanguageIso2(),
            $dataRow['translatable.lang'],
            "$type attribute language is not correctly exported"
        );

        $this->assertEquals(
            'string',
            $dataRow['type'],
            "$type attribute type is not correctly exported"
        );

        $this->assertEquals(
            'yes',
            $dataRow['published'],
            "$type attribute published is not correctly exported"
        );

        $this->assertEquals(
            'no', // text values are not options
            $dataRow['is_options'],
            "$type attribute is_options is not correctly exported"
        );
    }
}
