<?php

$product_class = StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler::CSS_CLASS_ADDON_PRODUCT;
$subproduct_class = StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler::CSS_CLASS_ADDON_SUBPRODUCT;

echo <<<HTML
 <style>
    .$subproduct_class td {
        padding-top: 0;
        padding-bottom: 0;
        font-size: 0.9em;
    }
</style>
HTML;
