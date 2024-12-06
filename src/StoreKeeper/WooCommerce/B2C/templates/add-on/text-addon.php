<?php
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler;
use StoreKeeper\WooCommerce\B2C\I18N;

$max_text_length = isset($addon['max_text_length']) ? $addon['max_text_length'] : '';
$is_required = ($addon['type'] === ProductAddOnHandler::ADDON_TYPE_REQUIRED_TEXT);

echo '<div class="sk-addon-select-text sk-addon-' . esc_attr($addon['type']) . ' ' . (!$is_required ? 'addon-text-optional' : '') .'" 
    ' . ProductAddOnHandler::FORM_DATA_SK_ADDON_GROUP_ID . '="' . esc_attr($addon['product_addon_group_id']) . '">';

echo '<label for="agree-text">';
if ($is_required) {
    echo '<input type="checkbox" id="agree-text" checked disabled>';
    echo '<strong style="vertical-align: middle">' . esc_html__('Required Text', I18N::DOMAIN) . '</strong>';
} else {
    echo '<input type="checkbox" name="agree" id="agree-text">';
    echo '<strong style="vertical-align: middle">' . esc_html__('Would you like text on the product?', I18N::DOMAIN) . '</strong>';
}
echo '</label>';

echo '<ul>';
foreach ($addon['options'] as $option) {
    echo '<li id="option-' . esc_attr($option['id']) . '">';
    echo esc_html($option['title']) .
        '<span style="font-size: 0.8em;">' .
        ($option['ppu_wt'] > 0
            ? '+' . esc_html(strip_tags(wc_price($option['ppu_wt'])))
            : ' (' . esc_html__('free', I18N::DOMAIN) . ')') .
        '</span>';

    echo '<textarea name="addon_text[' . esc_attr($addon['product_addon_group_id']) . '][' . esc_attr($option['id']) . '][' . esc_attr($addon['type']) . ']" 
            class="addon-text ' . ($is_required ? 'required' : '') . '" 
            maxlength="' . esc_attr($max_text_length) . '"' . ($is_required ? ' required' : '') . '></textarea>';
    echo '</li>';
}
echo '</ul>';
echo '</div>';
?>
