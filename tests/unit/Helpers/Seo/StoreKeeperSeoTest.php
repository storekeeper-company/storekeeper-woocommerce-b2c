<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Helpers\Seo;

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\StoreKeeperSeoHandler;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\StoreKeeperSeo;
use StoreKeeper\WooCommerce\B2C\UnitTest\AbstractTest;

class StoreKeeperSeoTest extends AbstractTest
{
    public function testProduct()
    {
        $product = \WC_Helper_Product::create_simple_product();

        $seo = StoreKeeperSeo::getProductSeo($product);
        $emptyExpect = [
            StoreKeeperSeo::SEO_TITLE => '',
            StoreKeeperSeo::SEO_DESCRIPTION => '',
            StoreKeeperSeo::SEO_KEYWORDS => '',
        ];
        $this->assertEquals(
            $emptyExpect,
            $seo,
            'Empty'
        );

        $title = uniqid('title');
        $description = uniqid('description');
        $keywords = uniqid('keywords');
        $expect = [
            StoreKeeperSeo::SEO_TITLE => $title,
            StoreKeeperSeo::SEO_DESCRIPTION => $description,
            StoreKeeperSeo::SEO_KEYWORDS => $keywords,
        ];
        $product = new \WC_Product($product->get_id());
        StoreKeeperSeo::setProductSeo($product, $title, $description, $keywords);

        $product = new \WC_Product($product->get_id());
        $seo = StoreKeeperSeo::getProductSeo($product);
        $this->assertEquals($expect, $seo, 'data set');

        $product = new \WC_Product($product->get_id());
        StoreKeeperSeo::setProductSeo($product, null, null, null);

        $product = new \WC_Product($product->get_id());
        $seo = StoreKeeperSeo::getProductSeo($product);
        $this->assertEquals($emptyExpect, $seo, 'Empty again');
    }

    public function testCategory()
    {
        /* @var $category \WP_Term */
        $category = \WC_Helper_Product::create_product_category();

        $seo = StoreKeeperSeo::getCategorySeo($category);
        $emptyExpect = [
            StoreKeeperSeo::SEO_TITLE => '',
            StoreKeeperSeo::SEO_DESCRIPTION => '',
            StoreKeeperSeo::SEO_KEYWORDS => '',
        ];
        $this->assertEquals(
            $emptyExpect,
            $seo,
            'Empty'
        );

        $title = uniqid('title');
        $description = uniqid('description');
        $keywords = uniqid('keywords');
        $expect = [
            StoreKeeperSeo::SEO_TITLE => $title,
            StoreKeeperSeo::SEO_DESCRIPTION => $description,
            StoreKeeperSeo::SEO_KEYWORDS => $keywords,
        ];
        $category = get_term($category->term_id);
        StoreKeeperSeo::setCategorySeo($category, $title, $description, $keywords);

        $category = get_term($category->term_id);
        $seo = StoreKeeperSeo::getCategorySeo($category);
        $this->assertEquals($expect, $seo, 'data set');

        $category = get_term($category->term_id);
        StoreKeeperSeo::setCategorySeo($category, null, null, null);

        $category = get_term($category->term_id);
        $seo = StoreKeeperSeo::getCategorySeo($category);
        $this->assertEquals($emptyExpect, $seo, 'Empty again');
    }

    public function providerGetBarcodeType()
    {
        return [
            ['gtin8', '40170725'],
            ['gtin12', '123601057072'],
            ['gtin14', '40700719670720'],
            ['isbn', '9781234567897'],
            ['isbn', '9791234567896'],
            ['gtin13', '4070071967072'],
            ['mpn', 'SK-102'],
        ];
    }

    /**
     * @dataProvider  providerGetBarcodeType
     */
    public function testGetBarcodeType($expect, $barcode)
    {
        $got = StoreKeeperSeoHandler::getBarcodeType($barcode);
        $this->assertEquals($expect, $got);
    }
}
