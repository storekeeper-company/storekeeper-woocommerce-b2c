<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\Query\ProductQueryBuilder;
use StoreKeeper\WooCommerce\B2C\Tools\Base36Coder;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;
use StoreKeeper\WooCommerce\B2C\Tools\Export\BlueprintExport;

class ProductBlueprintFileExport extends AbstractCSVFileExport
{
    public function getType(): string
    {
        return FileExportTypeHelper::PRODUCT_BLUEPRINT;
    }

    public function getPaths(): array
    {
        $paths = [
            'name' => 'Title',
            'alias' => 'Alias',
            'sku_pattern' => 'Sku pattern',
            'title_pattern' => 'Title pattern',
        ];

        foreach (AttributeExport::getAllAttributes() as $attribute) {
            $name = $attribute['name'];
            $label = $attribute['label'];
            $paths[$this->getConfigurablePath($name)] = "$label (Configurable)";
            $paths[$this->getSynchronizedPath($name)] = "$label (Synchronized)";
        }

        return $paths;
    }

    private function getConfigurablePath(string $name): string
    {
        $encodedName = Base36Coder::encode($name);

        return "attribute.encoded__$encodedName.is_configurable";
    }

    private function getSynchronizedPath(string $name): string
    {
        $encodedName = Base36Coder::encode($name);

        return "attribute.encoded__$encodedName.is_synchronized";
    }

    public function runExport(?string $exportLanguage = null): string
    {
        $exportedBlueprintAliases = [];

        $next = true;
        $index = 0;
        while ($next) {
            $product = $this->getVariableProductAtIndex($index++);
            $next = (bool) $product;

            if ($product) {
                $blueprintExport = new BlueprintExport($product);
                $blueprintAlias = $blueprintExport->getAlias();
                if (!in_array($blueprintExport->getAlias(), $exportedBlueprintAliases)) {
                    $lineData = [];

                    $lineData = $this->exportGenericInfo($lineData, $blueprintExport);
                    $lineData = $this->exportAttributes($lineData, $product);

                    $this->writeLineData($lineData);
                    $exportedBlueprintAliases[] = $blueprintAlias;
                }
            }
        }

        return $this->filePath;
    }

    private function getVariableProductAtIndex($index): ?\WC_Product_Variable
    {
        global $wpdb;

        $query = ProductQueryBuilder::getProductIdByProductType('variable', $index);
        $results = $wpdb->get_results($query);
        $result = current($results);
        if (false !== $result) {
            /** @var \WC_Product_Variable $product */
            $product = wc_get_product($result->product_id);
            if (false !== $product) {
                return $product;
            }
        }

        return null;
    }

    private function exportGenericInfo(array $lineData, BlueprintExport $blueprintExport): array
    {
        $lineData['name'] = $blueprintExport->getName();
        $lineData['alias'] = $blueprintExport->getAlias();
        $lineData['sku_pattern'] = $blueprintExport->getSkuPattern();
        $lineData['title_pattern'] = $blueprintExport->getTitlePattern();

        return $lineData;
    }

    private function exportAttributes(array $lineData, \WC_Product_Variable $product): array
    {
        foreach ($product->get_attributes() as $attribute) {
            $name = AttributeExport::getProductAttributeKey($attribute);
            $lineData[$this->getConfigurablePath($name)] = $attribute->get_variation();
            $lineData[$this->getSynchronizedPath($name)] = !$attribute->get_variation();
        }

        return $lineData;
    }
}
