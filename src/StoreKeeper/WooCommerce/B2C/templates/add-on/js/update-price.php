<?php

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler;

$additionalPrice = json_encode($price_addon_changes);
$fieldNameSelector = ProductAddOnHandler::INPUT_TYPE_PRODUCT_ADD_ON_SELECTOR;
$fieldSelectSelector = "select{$fieldNameSelector}";
$fieldCheckboxSelector = "{$fieldNameSelector} input[type=checkbox]";
$fieldSelector = "$fieldSelectSelector, $fieldCheckboxSelector";
$productSelector = "#product-$product_id";
$allowed_addon_groups = json_encode($allowed_addon_groups);

$groupSelector = ProductAddOnHandler::FORM_DATA_SK_ADDON_GROUP_ID;
$skGroupKey = ProductAddOnHandler::FORM_DATA_SK_ADDON_GROUP_ID_JS;

echo <<<HTML
<script type="text/javascript">

    jQuery(document).ready(function($) {
        var productId = $product_id;
        var originalPrice = {$start_price};
        var originalSalePrice = {$start_sale_price};
        var requiredPrice = {$required_price};
        var additionaPrice = {$additionalPrice};
        var allowedAddonGroups = $allowed_addon_groups;
        var addonGroups = $('[$groupSelector]');
        var regularPriceEl = $('$productSelector .woocommerce-Price-amount');
        var salePriceEl = $('$productSelector del .woocommerce-Price-amount');
        if( salePriceEl.length > 0 ) {
            regularPriceEl = $('$productSelector ins .woocommerce-Price-amount');
        } 
        
        function recalculatePrice(newPrice, newSalePrice) {
            newPrice += requiredPrice;
            newSalePrice += requiredPrice;
            var productGroupAddons = [];
            var otherGroupAddons = [];
            var productGroupIds = allowedAddonGroups[productId] || [];
            addonGroups.each(function(i, el) {
                var groupId = parseInt(el.dataset.$skGroupKey);
                if( productGroupIds.includes(groupId)){
                    productGroupAddons.push(el);
                } else {
                    otherGroupAddons.push(el);
                }
            });
            
            var productGroupAddonEl = $(productGroupAddons);
            
            $(otherGroupAddons).hide();
            productGroupAddonEl.show();
            
            productGroupAddonEl.find('{$fieldSelectSelector}').each(function(i, el) {
              var optionId = el.value;
              if( additionaPrice[optionId] ){
                  newPrice += additionaPrice[optionId];
                  newSalePrice += additionaPrice[optionId];
              }
            });
            
            productGroupAddonEl.find('{$fieldCheckboxSelector}:checked').each(function(i, el) {
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
            recalculatePrice(originalPrice,originalSalePrice);
        });
        
        
        var \$form = $('form.variations_form');
        \$form.on('found_variation', function(event, variation) {          
          productId = variation.variation_id;
          originalPrice = variation.display_regular_price;
          originalSalePrice = variation.display_price;
          
          recalculatePrice(originalPrice,originalSalePrice);
        });
        
        \$form.on('reset_data', function(event) {          
          originalPrice = {$start_price};
          originalSalePrice = {$start_sale_price};
          productId = $product_id;
          
          recalculatePrice(originalPrice,originalSalePrice);
        });
        
        // initial recalculation
        recalculatePrice(originalPrice,originalSalePrice);
    });
</script>

HTML;
