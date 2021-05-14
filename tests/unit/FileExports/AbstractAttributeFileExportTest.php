<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;
use WC_Helper_Product;
use WC_Meta_Box_Product_Data;

abstract class AbstractAttributeFileExportTest extends AbstractFileExportTest
{
    private function createProduct()
    {
        $product = WC_Helper_Product::create_simple_product(false);
        $attributeData = [
            'attribute_names' => ['Custom title one', 'Custom title multiple'],
            'attribute_position' => [0, 1],
            'attribute_visibility' => [1, 1],
            'attribute_values' => ['Custom value', 'Custom option 1|Custom option 2'],
        ];
        $attributes = WC_Meta_Box_Product_Data::prepare_attributes($attributeData);
        $product->set_attributes($attributes);
        $product->save();

        return wc_get_product($product->get_id());
    }

    protected function setupAttributes(): array
    {
        $sizeAttribute = WC_Helper_Product::create_attribute(
            'Size',
            [
                'S',
                'M',
                'L',
                'XL',
                'XXL',
            ]
        );

        $colourAttribute = WC_Helper_Product::create_attribute(
            'Colour',
            [
                'Red',
                'White',
                'Blue',
            ]
        );

        $brandAttribute = WC_Helper_Product::create_attribute(
            'brand',
            [
                'JavaScript',
                'TypeScript',
                'ReactJS',
            ]
        );

        FeaturedAttributeOptions::set(
            FeaturedAttributeOptions::getAttributeExportOptionConstant(
                FeaturedAttributeOptions::ALIAS_BRAND
            ),
            AttributeExport::getAttributeKey(
                $brandAttribute['attribute_name'],
                AttributeExport::TYPE_SYSTEM_ATTRIBUTE
            )
        );

        $product = $this->createProduct();

        return [$sizeAttribute, $colourAttribute, $brandAttribute, $product];
    }
}
