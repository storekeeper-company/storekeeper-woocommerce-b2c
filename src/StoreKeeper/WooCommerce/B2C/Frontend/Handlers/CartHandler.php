<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;
use WooCommerce;

class CartHandler
{
    public function addEmballageFee()
    {
        /* @var WooCommerce $woocommerce */
        global $woocommerce;

        $items = $woocommerce->cart->get_cart();

        $totalEmballagePrice = 0.00;
        foreach ($items as $values) {
            $product = wc_get_product($values['product_id']);
            $emballagePrice = $product->get_meta(ProductImport::PRODUCT_EMBALLAGE_PRICE_META_KEY);
            if ($emballagePrice) {
                $totalEmballagePrice += (float) $emballagePrice;
            }
        }

        if ($totalEmballagePrice > 0) {
            $woocommerce->cart->add_fee(__('Emballage fee', I18N::DOMAIN), $totalEmballagePrice);
        }
    }
}
