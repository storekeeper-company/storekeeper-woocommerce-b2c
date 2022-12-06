<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
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

        $lastEmballageTaxRateId = null;
        $totalEmballagePriceInCents = 0;
        foreach ($items as $values) {
            $product = wc_get_product($values['product_id']);
            $quantity = $values['quantity'];
            if ($product) {
                if ($product->meta_exists(ProductImport::PRODUCT_EMBALLAGE_PRICE_META_KEY)) {
                    $emballagePrice = $product->get_meta(ProductImport::PRODUCT_EMBALLAGE_PRICE_META_KEY);
                    $totalEmballagePriceInCents += round($emballagePrice * 100) * $quantity;
                }

                if ($product->meta_exists(ProductImport::PRODUCT_EMBALLAGE_TAX_ID_META_KEY)) {
                    $lastEmballageTaxRateId = $product->get_meta(ProductImport::PRODUCT_EMBALLAGE_TAX_ID_META_KEY);
                }
            }
        }

        if ($totalEmballagePriceInCents > 0) {
            $totalEmballagePrice = round($totalEmballagePriceInCents / 100, 2);
            $emballagePrice = [
                'name' => __('Emballage fee', I18N::DOMAIN),
                'amount' => $totalEmballagePrice,
                OrderExport::IS_EMBALLAGE_FEE_KEY => true,
            ];
            if ($lastEmballageTaxRateId) {
                $emballagePrice[OrderExport::TAX_RATE_ID_FEE_KEY] = $lastEmballageTaxRateId;
            }
            $woocommerce->cart->fees_api()->add_fee($emballagePrice);
        }
    }
}
