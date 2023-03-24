<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Helpers\Seo;

use StoreKeeper\WooCommerce\B2C\Helpers\Seo\StoreKeeperSeo;
use StoreKeeper\WooCommerce\B2C\UnitTest\AbstractTest;

class StoreKeeperSeoTest extends AbstractTest
{
    public function testProduct()
    {
        $product = \WC_Helper_Product::create_simple_product();

        $seo = StoreKeeperSeo::getProductSeo($product);
        $emptyExpect = [
            StoreKeeperSeo::META_TITLE => '',
            StoreKeeperSeo::META_DESCRIPTION => '',
            StoreKeeperSeo::META_KEYWORDS => '',
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
            StoreKeeperSeo::META_TITLE => $title,
            StoreKeeperSeo::META_DESCRIPTION => $description,
            StoreKeeperSeo::META_KEYWORDS => $keywords,
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
            StoreKeeperSeo::META_TITLE => '',
            StoreKeeperSeo::META_DESCRIPTION => '',
            StoreKeeperSeo::META_KEYWORDS => '',
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
            StoreKeeperSeo::META_TITLE => $title,
            StoreKeeperSeo::META_DESCRIPTION => $description,
            StoreKeeperSeo::META_KEYWORDS => $keywords,
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
}
