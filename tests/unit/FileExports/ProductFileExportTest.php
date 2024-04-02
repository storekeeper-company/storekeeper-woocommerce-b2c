<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\FileExports;

use StoreKeeper\WooCommerce\B2C\FileExport\AbstractCSVFileExport;
use StoreKeeper\WooCommerce\B2C\FileExport\ProductFileExport;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\StoreKeeperSeo;
use StoreKeeper\WooCommerce\B2C\Tools\Base36Coder;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use WC_Product;
use WC_Product_Variation;

class ProductFileExportTest extends AbstractFileExportTest
{
    public const BASE_COUNTRY = 'NL';

    public function getFileExportClass(): string
    {
        return ProductFileExport::class;
    }

    public function testProductExportTest()
    {
        // set country to NL
        update_option('woocommerce_default_country', self::BASE_COUNTRY);

        /**
         * @var \WC_Product_Variable $variableProduct
         * @var \WC_Product_Simple   $simpleProduct
         */
        [$simpleProduct, $variableProduct, $noImageProduct] = $this->setupTests();
        $noImageSeo = [
            StoreKeeperSeo::SEO_TITLE => uniqid('title'),
            StoreKeeperSeo::SEO_DESCRIPTION => uniqid('description'),
            StoreKeeperSeo::SEO_KEYWORDS => uniqid('keywords'),
        ];
        StoreKeeperSeo::setProductSeo($noImageProduct,
            $noImageSeo[StoreKeeperSeo::SEO_TITLE],
            $noImageSeo[StoreKeeperSeo::SEO_DESCRIPTION],
            $noImageSeo[StoreKeeperSeo::SEO_KEYWORDS]
        );
        $variableSeo = [
            StoreKeeperSeo::SEO_TITLE => uniqid('title'),
            StoreKeeperSeo::SEO_DESCRIPTION => uniqid('description'),
            StoreKeeperSeo::SEO_KEYWORDS => uniqid('keywords'),
        ];
        StoreKeeperSeo::setProductSeo($variableProduct,
            $variableSeo[StoreKeeperSeo::SEO_TITLE],
            $variableSeo[StoreKeeperSeo::SEO_DESCRIPTION],
            $variableSeo[StoreKeeperSeo::SEO_KEYWORDS]
        );

        $instance = $this->getNewFileExportInstance();
        $path = $instance->runExport(Language::getSiteLanguageIso2());
        $this->addFile($path);

        $spreadSheet = $this->readSpreadSheetFromPath($path);
        $mappedRows = $this->mapSpreadSheetRows(
            $spreadSheet,
            function ($row) {
                return $row['product.sku'];
            }
        );

        $this->assertProductExportRow(
            $noImageProduct,
            $mappedRows[$noImageProduct->get_sku()],
            'No image', null, $noImageSeo
        );
        $this->assertProductExportRow(
            $simpleProduct,
            $mappedRows[$simpleProduct->get_sku()],
            'Simple'
        );
        $this->assertProductExportRow(
            $variableProduct,
            $mappedRows[$variableProduct->get_sku()],
            'Variable', null, $variableSeo
        );
        // using @ because it's broken in woocomerce funtions
        // they say it's resolved https://github.com/woocommerce/woocommerce/issues/32985
        $WC_Product_Variations = @$variableProduct->get_available_variations();
        foreach ($WC_Product_Variations as $index => $variationProductData) {
            /* @var WC_Product_Variation $variationProduct */
            $variationProduct = new \WC_Product_Variation($variationProductData['variation_id']);

            $this->assertArrayHasKey(
                $variationProduct->get_sku(),
                $mappedRows,
                'Unknown variation product.'
            );

            $this->assertProductExportRow(
                $variationProduct,
                $mappedRows[$variationProduct->get_sku()],
                "Variation $index",
                $variableProduct
            );
        }
    }

    private function getProductTitle(WC_Product $product): string
    {
        if (self::WC_TYPE_ASSIGNED === $product->get_type()) {
            $values = array_map(
                function ($value) {
                    return $value;
                },
                $product->get_attributes()
            );

            return $product->get_title().' - '.implode(' ', $values);
        }

        return $product->get_title();
    }

    private function assertProductExportRow(WC_Product $product, array $productRow, string $type, ?WC_Product $parentProduct = null, array $seo = [])
    {
        $this->assertEquals(
            $this->getProductTitle($product),
            $productRow['title'],
            "$type product title is incorrectly exported"
        );

        $this->assertEquals(
            $product->get_sku(),
            $productRow['product.sku'],
            "$type product sku is incorrectly exported"
        );

        $this->assertEquals(
            $product->get_short_description(),
            $productRow['summary'],
            "$type product summary is incorrectly exported"
        );

        $this->assertEquals(
            $product->get_description(),
            $productRow['body'],
            "$type product body is incorrectly exported"
        );

        $this->assertEquals(
            ProductFileExport::getProductType($product),
            $productRow['product.type'],
            "$type product type is incorrectly exported"
        );

        $this->assertEquals(
            $product->get_slug(),
            $productRow['slug'],
            "$type product slug is incorrectly exported"
        );

        $this->assertEquals(
            ProductFileExport::getCategorySlugs($product),
            $productRow['extra_category_slugs'],
            "$type product category slugs is incorrectly exported"
        );

        $this->assertEquals(
            ProductFileExport::getTagSlugs($product),
            $productRow['extra_label_slugs'],
            "$type product tag slugs is incorrectly exported"
        );

        $taxRate = ProductFileExport::getTaxRate($product, self::BASE_COUNTRY);
        $this->assertEquals(
            $taxRate->tax_rate / 100,
            $productRow['product.product_price.tax'],
            "$type product tax rate is incorrectly exported"
        );

        $this->assertEquals(
            $taxRate->tax_rate_country,
            $productRow['product.product_price.tax_rate.country_iso2'],
            "$type product tax rate is incorrectly exported"
        );

        $got = [
            'seo_title' => $productRow['seo_title'],
            'seo_keywords' => $productRow['seo_keywords'],
            'seo_description' => $productRow['seo_description'],
        ];
        $this->assertArraySubset($seo, $got, true, "$type product seo");

        $stockValue = 0;
        if ($product->is_in_stock()) {
            $stockValue = $product->get_stock_quantity() ?? 1;
        }
        $this->assertEquals(
            $stockValue,
            $productRow['product.product_stock.value'],
            "$type product stock value is incorrectly exported"
        );

        $this->assertEquals(
            AbstractCSVFileExport::parseFieldValue('publish' === $product->get_status()),
            $productRow['product.active'],
            "$type product active is incorrectly exported"
        );

        $this->assertEquals(
            AbstractCSVFileExport::parseFieldValue($product->is_in_stock()),
            $productRow['product.product_stock.in_stock'],
            "$type product in stock is incorrectly exported"
        );

        $this->assertEquals(
            AbstractCSVFileExport::parseFieldValue(!$product->get_manage_stock()),
            $productRow['product.product_stock.unlimited'],
            "$type product unlimited is incorrectly exported"
        );

        $this->assertEquals(
            AbstractCSVFileExport::parseFieldValue(!$product->get_backorders()),
            $productRow['shop_products.main.backorder_enabled'],
            "$type product backorder-able is incorrectly exported"
        );

        $imageIds = array_merge(
            [
                $product->get_image_id(),
            ],
            $product->get_gallery_image_ids()
        );

        $imageIds = array_filter(
            $imageIds,
            function ($image) {
                return !empty($image);
            }
        );

        if ($parentProduct) {
            $parentProductImageIds = array_merge(
                [
                    $product->get_image_id(),
                ],
                $product->get_gallery_image_ids()
            );

            $imageIds = array_diff($imageIds, $parentProductImageIds);
        }

        foreach ($imageIds as $index => $imageId) {
            $this->assertEquals(
                true,
                key_exists("product.product_images.$index.download_url", $productRow),
                "$type product image (index=$index) key does not exists"
            );

            $q = AbstractCSVFileExport::parseFieldValue(wp_get_attachment_url($imageId));

            $this->assertEquals(
                AbstractCSVFileExport::parseFieldValue(wp_get_attachment_url($imageId)),
                $productRow["product.product_images.$index.download_url"],
                "$type product image is incorrectly exported"
            );
        }

        if (self::WC_TYPE_ASSIGNED === $product->get_type()) {
            $this->assertAssignedAttributes($product, $productRow, $type);
        } else {
            $this->assertAttributes($product, $productRow, $type);
        }
    }

    private function assertAssignedAttributes(WC_Product_Variation $product, array $productRow, string $type)
    {
        $parentProduct = wc_get_product($product->get_parent_id());
        $parentAttributes = $parentProduct->get_attributes();
        foreach ($product->get_attributes() as $alias => $value) {
            $parentAttribute = $parentAttributes[$alias];
            $name = AttributeExport::getProductAttributeKey($parentAttribute);
            $encodedName = Base36Coder::encode($name);
            $label = AttributeExport::getProductAttributeLabel($parentAttribute);
            $this->assertEquals(
                $value,
                $productRow["content_vars.encoded__$encodedName.value_label"],
                "$type product attribute $label's label is not exported correctly"
            );

            $this->assertEquals(
                sanitize_title($value),
                $productRow["content_vars.encoded__$encodedName.value"],
                "$type product attribute $label's value is not exported correctly"
            );
        }
    }

    private function assertAttributes(WC_Product $product, array $productRow, string $type)
    {
        foreach ($product->get_attributes() as $alias => $attribute) {
            $name = AttributeExport::getProductAttributeKey($attribute);
            $encodedName = Base36Coder::encode($name);
            $label = AttributeExport::getProductAttributeLabel($attribute);
            if (ProductFileExport::isAttributeWithOptions($alias)) {
                $options = $attribute->get_options();
                $option = array_shift($options);
                $valueLabel = $option;
                $value = sanitize_title($option);
                if (0 !== $attribute->get_id()) {
                    $terms = get_terms(
                        $alias,
                        [
                            'hide_empty' => false,
                            'include' => [$value],
                        ]
                    );
                    $term = array_shift($terms);
                    $valueLabel = $term->name;
                    $value = $term->slug;
                }

                $this->assertEquals(
                    $valueLabel,
                    $productRow["content_vars.encoded__$encodedName.value_label"],
                    "$type product attribute $label's label is not exported correctly"
                );

                $this->assertEquals(
                    $value,
                    $productRow["content_vars.encoded__$encodedName.value"],
                    "$type product attribute $label's value is not exported correctly"
                );
            } else {
                $options = $attribute->get_options();
                $option = array_shift($options);
                $this->assertEquals(
                    $option,
                    $productRow["content_vars.encoded__$encodedName.value"],
                    "$type product attribute $label's raw value is not exported correctly"
                );
            }
        }
    }

    private function setupTests(): array
    {
        list($taxRate21, $taxRate9) = $this->createTaxRates();
        $simpleProduct = $this->createSimpleProductWithTagAndCategory($taxRate21);
        $this->addImageToProduct($simpleProduct);
        $variableProduct = $this->createVariableProduct($taxRate21);
        $this->addImageToProduct($variableProduct);
        $noImageProduct = $this->createSimpleProduct('no image product', $taxRate9);

        return [$simpleProduct, $variableProduct, $noImageProduct, $taxRate21, $taxRate9];
    }
}
