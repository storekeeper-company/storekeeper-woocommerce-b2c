<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Helpers\Seo\StoreKeeperSeo;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Tools\FeaturedAttributes;

class StoreKeeperSeoHandler implements WithHooksInterface
{
    public function registerHooks(): void
    {
        if (StoreKeeperSeo::isSelectedHandler()) {
            add_filter('woocommerce_structured_data_product', [$this, 'setProductStructuredData'], 10, 2);
            add_action('wp_head', [$this, 'addMetaTags']);
            add_action('document_title_parts', [$this, 'setTitle'], 10);
        }
    }

    protected function getCurrentProduct(): ?\WC_Product
    {
        if (is_product()) {
            $product = wc_get_product();
            if ($product instanceof \WC_Product) {
                return $product;
            }
        }

        return null;
    }

    public function setTitle($title)
    {
        $product = $this->getCurrentProduct();
        if (!is_null($product)) {
            $seo = StoreKeeperSeo::getProductSeo($product);
            if (!empty($seo[StoreKeeperSeo::SEO_TITLE])) {
                $title['title'] = $seo[StoreKeeperSeo::SEO_TITLE];
            }
        } elseif (is_product_category()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $seo = StoreKeeperSeo::getCategorySeo($term);
                if (!empty($seo[StoreKeeperSeo::SEO_TITLE])) {
                    $title['title'] = $seo[StoreKeeperSeo::SEO_TITLE];
                }
            }
        }

        return $title;
    }

    public function addMetaTags()
    {
        $product = $this->getCurrentProduct();
        if (!is_null($product)) {
            $seo = StoreKeeperSeo::getProductSeo($product);
            $this->renderHeadMetaSeo($seo);
        } elseif (is_product_category()) {
            $term = get_queried_object();
            if ($term instanceof \WP_Term) {
                $seo = StoreKeeperSeo::getCategorySeo($term);
                $this->renderHeadMetaSeo($seo);
            }
        }
    }

    public function setProductStructuredData($markdown, $product)
    {
        /* @var $product \WC_Product */
        $this->applyBrand($markdown, $product);
        $this->applyBarcode($markdown, $product);

        foreach ($markdown['offers'] as &$offer) {
            if (isset($offer['priceSpecification']['valueAddedTaxIncluded'])) {
                $offer['priceSpecification']['valueAddedTaxIncluded'] = true; // we always set with tax included
            }
            // seems google does not handle https:// and woommerce does not want to change
            // see https://support.google.com/webmasters/thread/3210194?hl=en
            if (!empty($offer['availability'])) {
                $offer['availability'] = str_replace(
                    'https://schema.org/',
                    'http://schema.org/',
                    $offer['availability']
                );
            }

            // get minimum price instead of first variation
            if ($product instanceof \WC_Product_Variable) {
                $offer['price'] = $product->get_variation_price('min', false);
            }
        }

        return $markdown;
    }

    /**
     * @param $markdown
     * @param $product
     * @param array $featured_attrs
     */
    protected function applyBarcode(&$markdown, $product)
    {
        $barcode_name = FeaturedAttributeOptions::getWooCommerceAttributeName(FeaturedAttributes::ALIAS_BARCODE);
        if (!empty($barcode_name)) {
            $barcode = $product->get_attribute($barcode_name);
            if (!empty($barcode)) {
                $matched = false;
                if (preg_match('/^[\d\-\s]{8,}$/', $barcode)) {
                    // only numbers (space and minus)
                    $barcode = preg_replace('/[-\s]/', '', $barcode);
                    if (8 === strlen($barcode)) {
                        $markdown['gtin8'] = $barcode;
                        $matched = true;
                    } else {
                        if (12 === strlen($barcode)) {
                            $markdown['gtin12'] = $barcode;
                            $matched = true;
                        } else {
                            if (14 === strlen($barcode)) {
                                $markdown['gtin14'] = $barcode;
                                $matched = true;
                            } else {
                                if (13 === strlen($barcode)) {
                                    // check prefixes for ISBN
                                    // see: https://en.wikipedia.org/wiki/International_Standard_Book_Number#Overview
                                    $first3 = substr($barcode, 0, 3);
                                    if ('978' === $first3 || '979' === $first3) {
                                        $markdown['isbn'] = $barcode;
                                        $matched = true;
                                    } else {
                                        $markdown['gtin13'] = $barcode;
                                        $matched = true;
                                    }
                                }
                            }
                        }
                    }
                }
                if (!$matched) {
                    $markdown['mpn'] = $barcode;
                }
            }
        }
    }

    /**
     * @param $markdown
     * @param $product
     * @param array $featured_attrs
     */
    protected function applyBrand(&$markdown, $product)
    {
        $brand_name = FeaturedAttributeOptions::getWooCommerceAttributeName(FeaturedAttributes::ALIAS_BRAND);
        if (!empty($brand_name)) {
            $brand = $product->get_attribute($brand_name);
            if (!empty($brand)) {
                $markdown['brand'] = $brand;
            }
        }
    }

    protected function renderHeadMetaSeo(array $seo): void
    {
        $kw = esc_attr($seo[StoreKeeperSeo::SEO_KEYWORDS]);
        $description = esc_attr($seo[StoreKeeperSeo::SEO_DESCRIPTION]);
        echo <<<HTML
  <meta name="description" content="$description">
  <meta name="keywords" content="$kw">
HTML;
    }
}