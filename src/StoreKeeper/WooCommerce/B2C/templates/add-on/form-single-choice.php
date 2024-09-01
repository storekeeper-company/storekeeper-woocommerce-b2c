<?php

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler;

$form_field_id = $addon[ProductAddOnHandler::KEY_FORM_ID];

echo '<div class="sk-addon-select sk-addon-'.$addon['type'].'" 
    '.ProductAddOnHandler::FORM_DATA_SK_ADDON_GROUP_ID.'="'.$addon['product_addon_group_id'].'">';

woocommerce_form_field(
    $form_field_id,
    $addon[ProductAddOnHandler::KEY_FORM_OPTIONS]
);
echo '</div>';

$fieldNameSelector = ProductAddOnHandler::INPUT_TYPE_PRODUCT_ADD_ON_SELECTOR;
$fieldSelectSelector = "select{$fieldNameSelector}[name=\"$form_field_id\"]";

$out_of_stock = $addon[ProductAddOnHandler::KEY_OUT_OF_STOCK_OPTION_IDS];
if (!empty($out_of_stock)) {
    $out_of_stock_ids = json_encode($out_of_stock);
    $out_of_stock_str = json_encode(' - '.__('Out of stock', 'woocommerce'));
    echo <<<HTML
<script type="text/javascript">
    jQuery(document).ready(function($) {
        var formSelect = $('{$fieldSelectSelector} option');
        var outOfStockIds = $out_of_stock_ids;
        formSelect.each(function(i, el) {
            if( outOfStockIds.includes(parseInt(el.value)) ) {
                var elObj = $(el);
                elObj.prop('disabled', true)
                    .text(elObj.text()+$out_of_stock_str);
            }            
        });
    });
</script>
HTML;
}
