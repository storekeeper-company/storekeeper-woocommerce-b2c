<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\AbstractCSVFileExport;
use StoreKeeper\WooCommerce\B2C\FileExport\ProductBlueprintFileExport;
use StoreKeeper\WooCommerce\B2C\Tools\Base36Coder;
use StoreKeeper\WooCommerce\B2C\Tools\CommonAttributeName;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;
use StoreKeeper\WooCommerce\B2C\Tools\Export\BlueprintExport;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use WC_Helper_Product;

class ProductBlueprintFileExportTest extends AbstractFileExportTest
{
    public function getFileExportClass(): string
    {
        return ProductBlueprintFileExport::class;
    }

    public function testProductBlueprintExportTest()
    {
        $productVariable = WC_Helper_Product::create_variation_product();

        $instance = $this->getNewFileExportInstance();
        $path = $instance->runExport(Language::getSiteLanguageIso2());
        $this->addFile($path);

        $spreadSheet = $this->readSpreadSheetFromPath($path);
        $mappedRows = $this->mapSpreadSheetRows(
            $spreadSheet,
            function ($row) {
                return $row['alias'];
            }
        );

        $blueprint = new BlueprintExport($productVariable);
        $mappedRow = $mappedRows[$blueprint->getAlias()];

        $this->assertEquals(
            $blueprint->getName(),
            $mappedRow['name'],
            'Blueprint name is not exported correctly'
        );

        $this->assertEquals(
            $blueprint->getAlias(),
            $mappedRow['alias'],
            'Blueprint alias is not exported correctly'
        );

        $this->assertEquals(
            $blueprint->getTitlePattern(),
            $mappedRow['title_pattern'],
            'Blueprint alias is not exported correctly'
        );

        $this->assertEquals(
            $blueprint->getSkuPattern(),
            $mappedRow['sku_pattern'],
            'Blueprint alias is not exported correctly'
        );

        foreach ($productVariable->get_variation_attributes() as $attributeName => $_) {
            $name = AttributeExport::getAttributeKey($attributeName, CommonAttributeName::TYPE_SYSTEM_ATTRIBUTE);
            $encoded = Base36Coder::encode($name);

            $this->assertEquals(
                AbstractCSVFileExport::parseFieldValue(true),
                $mappedRow["attribute.encoded__$encoded.is_configurable"],
                "Blueprint attribute $name is not exported correctly"
            );

            $this->assertEquals(
                AbstractCSVFileExport::parseFieldValue(false),
                $mappedRow["attribute.encoded__$encoded.is_synchronized"],
                "Blueprint attribute $name is not exported correctly"
            );
        }
    }
}
