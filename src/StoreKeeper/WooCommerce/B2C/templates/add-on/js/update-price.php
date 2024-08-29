<?php

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler;


$additionalPrice = json_encode($price_addon_changes);
$fieldNameSelector = ProductAddOnHandler::INPUT_TYPE_PRODUCT_ADD_ON_SELECTOR;
$fieldSelectSelector = "select{$fieldNameSelector}";
$fieldCheckboxSelector = "{$fieldNameSelector} input[type=checkbox]";
$fieldSelector = "$fieldSelectSelector, $fieldCheckboxSelector";

echo <<<HTML
<script type="text/javascript">
    jQuery(document).ready(function($) {
        var originalPrice = {$start_price};
        var additionaPrice = {$additionalPrice};
        
        function recalculatePrice() {
            var newPrice = originalPrice;
            
            $('{$fieldSelectSelector}').each(function(i, el) {
              var optionId = el.value;
              if( additionaPrice[optionId] ){
                  newPrice += additionaPrice[optionId];
              }
            });
            
            $('{$fieldCheckboxSelector}:checked').each(function(i, el) {
              var optionId = el.value;
              if( additionaPrice[optionId] ){
                  newPrice += additionaPrice[optionId];
              }
            });

            // todo fix discount price
            $('.woocommerce-Price-amount').html(
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
