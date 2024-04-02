<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeExportOptions;
use StoreKeeper\WooCommerce\B2C\Tools\CommonAttributeName;
use StoreKeeper\WooCommerce\B2C\Tools\FeaturedAttributes;

abstract class AbstractAttributeFileExportTest extends AbstractFileExportTest
{
    public const QTY_ATTRIBUTE_NAME = 'quantino_no_exportino';
    public const BRAND_ATTRIBUTE_NAME = 'brandino_exportino';

    public const SA_COLOUR = 'sa_colour';
    public const SA_SIZE = 'sa_size';
    public const SA_BRAND = FeaturedAttributes::ALIAS_BRAND;
    public const SA_QTY = FeaturedAttributes::ALIAS_IN_BOX_QTY;
    public const SA_QTY_ATTR = 'sa_'.self::QTY_ATTRIBUTE_NAME;
    public const SA_BRAND_ATTR = 'sa_'.self::BRAND_ATTRIBUTE_NAME;
    public const CA_CUSTOM_TITLE_ONE = 'ca_custom-title-one';
    public const CA_CUSTOM_TITLE_MULTIPLE = 'ca_custom-title-multiple';

    private function createProduct()
    {
        $product = \WC_Helper_Product::create_simple_product(false);
        $attributeData = [
            'attribute_names' => ['Custom title one', 'Custom title multiple'],
            'attribute_position' => [0, 1],
            'attribute_visibility' => [1, 1],
            'attribute_values' => ['Custom value', 'Custom option 1|Custom option 2'],
        ];
        $attributes = \WC_Meta_Box_Product_Data::prepare_attributes($attributeData);
        $product->set_attributes($attributes);
        $product->save();

        return wc_get_product($product->get_id());
    }

    protected function setupAttributes(): array
    {
        $sizeAttribute = \WC_Helper_Product::create_attribute(
            'Size',
            [
                'S',
                'M',
                'L',
                'XL',
                'XXL',
            ]
        );

        $colourAttribute = \WC_Helper_Product::create_attribute(
            'Colour',
            [
                'Red',
                'White',
                'Blue',
            ]
        );

        $brandAttribute = \WC_Helper_Product::create_attribute(
            self::BRAND_ATTRIBUTE_NAME,
            [
                'JavaScript',
                'TypeScript',
                'ReactJS',
            ]
        );

        FeaturedAttributeExportOptions::set(
            FeaturedAttributeExportOptions::getAttributeExportOptionConstant(
                FeaturedAttributes::ALIAS_BRAND
            ),
            CommonAttributeName::getSystemName($brandAttribute['attribute_name'])
        );

        $qtyAttribute = \WC_Helper_Product::create_attribute(
            self::QTY_ATTRIBUTE_NAME,
            [
                1,
                12,
                24,
            ]
        );

        FeaturedAttributeExportOptions::set(
            FeaturedAttributeExportOptions::getAttributeExportOptionConstant(
                FeaturedAttributes::ALIAS_IN_BOX_QTY
            ),
            CommonAttributeName::getSystemName($qtyAttribute['attribute_name'])
        );

        $product = $this->createProduct();

        return [$sizeAttribute, $colourAttribute, $qtyAttribute, $brandAttribute, $product];
    }
}
