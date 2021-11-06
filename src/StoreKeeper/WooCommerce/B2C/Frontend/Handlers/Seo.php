<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Tools\FeaturedAttributes;

class Seo
{
    public function addExtraSeoData($markdown, $product)
    {
        // one of
        /* @var $product \WC_Product_Variable */
        /* @var $product \WC_Product_Simple */
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
                if (preg_match('/^[\d-\s]{8,}$/', $barcode)) {
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
}
