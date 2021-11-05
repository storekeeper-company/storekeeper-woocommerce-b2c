<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;
use WC_Helper_Product;
use WC_Meta_Box_Product_Data;

abstract class AbstractAttributeFileExportTest extends AbstractFileExportTest
{
    const QTY_ATTRIBUTE_NAME = 'quantino_no_exportino';
    const BRAND_ATTRIBUTE_NAME = 'brandino_exportino';

    const SA_COLOUR = 'sa_colour';
    const SA_SIZE = 'sa_size';
    const SA_BRAND = FeaturedAttributeOptions::ALIAS_BRAND;
    const SA_QTY = FeaturedAttributeOptions::ALIAS_IN_BOX_QTY;
    const SA_QTY_ATTR = 'sa_'.self::QTY_ATTRIBUTE_NAME;
    const SA_BRAND_ATTR = 'sa_'.self::BRAND_ATTRIBUTE_NAME;
    const CA_CUSTOM_TITLE_ONE = 'ca_custom-title-one';
    const CA_CUSTOM_TITLE_MULTIPLE = 'ca_custom-title-multiple';

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
            self::BRAND_ATTRIBUTE_NAME,
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

        $qtyAttribute = WC_Helper_Product::create_attribute(
            self::QTY_ATTRIBUTE_NAME,
            [
                1,
                12,
                24,
            ]
        );

        FeaturedAttributeOptions::set(
            FeaturedAttributeOptions::getAttributeExportOptionConstant(
                FeaturedAttributeOptions::ALIAS_IN_BOX_QTY
            ),
            AttributeExport::getAttributeKey(
                $qtyAttribute['attribute_name'],
                AttributeExport::TYPE_SYSTEM_ATTRIBUTE
            )
        );

        $product = $this->createProduct();

        return [$sizeAttribute, $colourAttribute, $qtyAttribute, $brandAttribute, $product];
    }
}
