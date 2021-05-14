<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\AttributeOptionsFileExport;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use WC_Product;
use WC_Product_Attribute;

class AttributeOptionFileExportTest extends AbstractAttributeFileExportTest
{
    public function getFileExportClass(): string
    {
        return AttributeOptionsFileExport::class;
    }

    public function testAttributeOptionExportTest()
    {
        list($sizeAttributeData, $colourAttributeData, $brandAttribute, $product) = $this->setupAttributes();

        $instance = $this->getNewFileExportInstance();
        $path = $instance->runExport(Language::getSiteLanguageIso2());
        $this->addFile($path);

        $spreadSheet = $this->readSpreadSheetFromPath($path);
        $mappedRows = $this->mapSpreadSheetRows(
            $spreadSheet,
            function ($row) {
                $slug = sanitize_title($row['name']);
                $attributeName = 'pa_'.substr($row['attribute.name'], 3);

                return "$slug::$attributeName";
            }
        );

        $this->assertSystemAttributeOptionsEmpty($mappedRows, $brandAttribute['term_ids'], 'Brand');
        $this->assertSystemAttributeOptions($mappedRows, $sizeAttributeData['term_ids'], 'Size');
        $this->assertSystemAttributeOptions($mappedRows, $colourAttributeData['term_ids'], 'Colour');
        $this->assertProductAttributeOption($mappedRows, $product, 'Simple');
    }

    private function assertSystemAttributeOptions(array $mappedRows, array $termIds, string $type)
    {
        foreach ($termIds as $termId) {
            $term = get_term($termId);
            $attributeMap = $this->getAttributeMap();
            $attribute = $attributeMap[$term->taxonomy];
            $mappedRow = $mappedRows["$term->slug::$term->taxonomy"];

            $this->assertEquals(
                $term->name,
                $mappedRow['label'],
                "$type attribute option's label is incorrect"
            );

            $this->assertEquals(
                $term->slug,
                $mappedRow['name'],
                "$type attribute option's name is incorrect"
            );

            $this->assertEquals(
                Language::getSiteLanguageIso2(),
                $mappedRow['translatable.lang'],
                "$type attribute option's language is incorrect"
            );

            $this->assertEquals(
                AttributeExport::getAttributeKey($attribute->attribute_name, AttributeExport::TYPE_SYSTEM_ATTRIBUTE),
                $mappedRow['attribute.name'],
                "$type attribute option's name is incorrect"
            );

            $this->assertEquals(
                $attribute->attribute_label,
                $mappedRow['attribute.label'],
                "$type attribute option's name is incorrect"
            );
        }
    }

    private function assertSystemAttributeOptionsEmpty(array $mappedRows, array $termIds, string $type)
    {
        foreach ($termIds as $termId) {
            $term = get_term($termId);
            $this->assertEmpty($mappedRows["$term->slug::$term->taxonomy"], "$type options should not be exported");
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

    private function assertProductAttributeOption(array $mappedRows, WC_Product $product, string $type)
    {
        /** @var WC_Product_Attribute $attribute */
        foreach ($product->get_attributes() as $attribute) {
            if (count($attribute->get_options()) > 1) {
                foreach ($attribute->get_options() as $option) {
                    $optionSlug = sanitize_title($option);
                    $attributeSlug = 'pa_'.sanitize_title($attribute->get_name());
                    $mappedRow = $mappedRows["$optionSlug::$attributeSlug"];

                    $this->assertEquals(
                        $option,
                        $mappedRow['label'],
                        "$type attribute option's label is incorrect"
                    );

                    $this->assertEquals(
                        $optionSlug,
                        $mappedRow['name'],
                        "$type attribute option's name is incorrect"
                    );

                    $this->assertEquals(
                        Language::getSiteLanguageIso2(),
                        $mappedRow['translatable.lang'],
                        "$type attribute option's language is incorrect"
                    );

                    $this->assertEquals(
                        AttributeExport::getAttributeKey($attributeSlug, AttributeExport::TYPE_CUSTOM_ATTRIBUTE),
                        $mappedRow['attribute.name'],
                        "$type attribute option's name is incorrect"
                    );

                    $this->assertEquals(
                        $attribute->get_name(),
                        $mappedRow['attribute.label'],
                        "$type attribute option's name is incorrect"
                    );
                }
            }
        }
    }
}
