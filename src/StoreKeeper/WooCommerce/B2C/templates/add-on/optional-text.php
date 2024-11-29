<?php

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler;
use StoreKeeper\WooCommerce\B2C\I18N;

$max_text_length = isset($addon['max_text_length']) ? $addon['max_text_length'] : '';

echo '<div class="sk-addon-select sk-addon-' . esc_attr($addon['type']) . '" 
    ' . ProductAddOnHandler::FORM_DATA_SK_ADDON_GROUP_ID . '="' . esc_attr($addon['product_addon_group_id']) . '">';

echo '<label for="agree">
        <input type="checkbox" name="agree" id="agree"> <strong>'. __(
                'Would you like a text on the product?', I18N::DOMAIN
            ) .'</strong>
      </label>';

echo '<ul>';
foreach ($addon['options'] as $option) {
    echo '<li id="option-' . esc_attr($option['id']) . '">';
    echo esc_html($option['title']) .
        '<span style="font-size: 0.8em;">' .
        ($option['ppu_wt'] > 0
            ? '+' .esc_html(strip_tags(wc_price($option['ppu_wt'])))
            : ' (' . __('free',  I18N::DOMAIN) . ')') .
        '</span>';
    echo '<textarea name="addon_text[' . esc_attr($addon['product_addon_group_id']) . '][' . esc_attr($option['id']) . '][' . esc_attr(ProductAddOnHandler::ADDON_TYPE_REQUIRED_TEXT) . ']" 
            class="addon-text" maxlength="' . esc_attr($max_text_length) . '" ></textarea>';
    echo '</li>';
}
echo '</ul>';
echo '</div>';
?>
