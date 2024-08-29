<?php

$additionaPrice = json_encode($price_addon_changes);
$fieldNameSelector = '[data-sk-type="'.StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler::INPUT_TYPE_PRODUCT_ADD_ON.'"]';
$fieldSelectSelector = "select{$fieldNameSelector}";
$fieldCheckboxSelector = "{$fieldNameSelector} input[type=checkbox]";
$fieldSelector = "$fieldSelectSelector, $fieldCheckboxSelector";

echo <<<HTML
<script type="text/javascript">
    jQuery(document).ready(function($) {
        var originalPrice = {$start_price};
        var additionaPrice = {$additionaPrice};
        
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

            // todo fix the price format
            // todo fix discount price
            $('.woocommerce-Price-amount').html('<span class="woocommerce-Price-currencySymbol">$</span>' + newPrice.toFixed(2));
        }
        
        $('{$fieldSelector}').change(function() {
            recalculatePrice();
        });
        recalculatePrice();
    });
</script>

HTML;
