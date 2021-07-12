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
        list($sizeAttributeData, $colourAttributeData, $brandAttribute, $product) = $this->setupAttributes();

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

        $this->assertArrayNotHasKey('sa_brand', $mappedRows, 'Brand should not be exported.');
        $this->assertSystemAttribute($colourAttributeData, $mappedRows['sa_colour'], 'Colour');
        $this->assertSystemAttribute($sizeAttributeData, $mappedRows['sa_size'], 'Size');
        $this->assertProductAttribute(
            $product->get_attributes()['custom-title-one'],
            $mappedRows['ca_custom-title-one'],
            'Single option'
        );
        $this->assertProductAttribute(
            $product->get_attributes()['custom-title-multiple'],
            $mappedRows['ca_custom-title-multiple'],
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
            count($attribute->get_options()) > 1 ? 'yes' : 'no',
            $dataRow['is_options'],
            "$type attribute is_options is not correctly exported"
        );
    }
}
