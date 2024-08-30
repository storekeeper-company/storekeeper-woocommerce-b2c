<?php

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler;

$additionalPrice = json_encode($price_addon_changes);
$fieldNameSelector = ProductAddOnHandler::INPUT_TYPE_PRODUCT_ADD_ON_SELECTOR;
$fieldSelectSelector = "select{$fieldNameSelector}";
$fieldCheckboxSelector = "{$fieldNameSelector} input[type=checkbox]";
$fieldSelector = "$fieldSelectSelector, $fieldCheckboxSelector";
$productSelector = "#product-$product_id";

echo <<<HTML
<script type="text/javascript">
    jQuery(document).ready(function($) {
        var originalPrice = {$start_price};
        var originalSalePrice = {$start_sale_price};
        var additionaPrice = {$additionalPrice};
        var regularPriceEl = $('$productSelector .woocommerce-Price-amount');
        var salePriceEl = $('$productSelector del .woocommerce-Price-amount');
        if( salePriceEl.length > 0 ) {
            regularPriceEl = $('$productSelector ins .woocommerce-Price-amount');
        } 
        
        function recalculatePrice() {
            var newPrice = originalPrice;
            var newSalePrice = originalSalePrice;
            
            $('{$fieldSelectSelector}').each(function(i, el) {
              var optionId = el.value;
              if( additionaPrice[optionId] ){
                  newPrice += additionaPrice[optionId];
                  newSalePrice += additionaPrice[optionId];
              }
            });
            
            $('{$fieldCheckboxSelector}:checked').each(function(i, el) {
              var optionId = el.value;
              if( additionaPrice[optionId] ){
                  newPrice += additionaPrice[optionId];
                  newSalePrice += additionaPrice[optionId];
              }
            });

            salePriceEl.html(
                wc_price_js( newSalePrice, wc_settings_args ) 
            );   
            regularPriceEl.html(
                wc_price_js( newPrice, wc_settings_args ) 
            );   
        }
        
        $('{$fieldSelector}').change(function() {
            recalculatePrice();
        });
        recalculatePrice();
    });
</script>

HTML;
