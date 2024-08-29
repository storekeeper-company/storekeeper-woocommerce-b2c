<?php

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler;

$out_of_stock_str = json_encode(' - '.__('Out of stock', 'woocommerce'));

echo '<p class="custom-checkbox-description">'.$addon['title'].'</p>';  // todo style better
foreach ($addon['options'] as $option) {
    woocommerce_form_field(
        $option[ProductAddOnHandler::KEY_FORM_ID],
        $option[ProductAddOnHandler::KEY_FORM_OPTIONS]
    );
}

$out_of_stock = $addon[ProductAddOnHandler::KEY_OUT_OF_STOCK_OPTION_IDS];
if (!empty($out_of_stock)) {
    $fieldNameSelector = ProductAddOnHandler::INPUT_TYPE_PRODUCT_ADD_ON_SELECTOR;
    $fieldNameSelector .= '['.ProductAddOnHandler::FORM_DATA_SK_ADDON.'="'.$addon[ProductAddOnHandler::KEY_FORM_ID].'"]';
    $fieldSelectSelector = json_encode("label{$fieldNameSelector}");

    $fieldDisabledSelector = implode(',', array_map(
        function ($id) {
            return 'input[value="'.$id.'"]';
        },
        $out_of_stock
    ));
    $fieldDisabledSelector = json_encode($fieldDisabledSelector);

    $out_of_stock_ids = json_encode($out_of_stock);
    $out_of_stock_str = json_encode(' - '.__('Out of stock', 'woocommerce'));

    echo <<<HTML
<script type="text/javascript">
    jQuery(document).ready(function($) {
        var formSelect = $($fieldSelectSelector);
        var outOfStockIds = $out_of_stock_ids;
        
        formSelect.each(function(i, el) {
            var elObj = $(el);
            var disabledCheckbox = elObj.find($fieldDisabledSelector);
            if( disabledCheckbox.length > 0 ) {
                disabledCheckbox.prop('disabled', true);
                elObj.append($out_of_stock_str);
            }            
        });
    });
</script>
HTML;
}
