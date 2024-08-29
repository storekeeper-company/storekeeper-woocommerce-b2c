<?php

$product_class = StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler::CSS_CLASS_ADDON_PRODUCT;
$subproduct_class = StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler::CSS_CLASS_ADDON_SUBPRODUCT;

$icon_url = StoreKeeper\WooCommerce\B2C\Core::plugin_url().'/assets/images/arrow-turn-down-left.svg';
echo <<<HTML
 <style>
    .$subproduct_class td {
        padding-top: 0;
        padding-bottom: 0;
        font-size: 0.9em;
    }
    .$subproduct_class td.product-name {
        padding-left: 32px;
    }
    .$subproduct_class td.product-name .price::after,
    .$subproduct_class td.product-subtotal .amount::after{
          content: '';
          display: inline-block;
          width: 20px;
          height: 12px;
          background-image: url($icon_url);
          background-size: contain;
          background-repeat: no-repeat;
          background-position: center right;
          opacity: .25;
    }
</style>
HTML;
