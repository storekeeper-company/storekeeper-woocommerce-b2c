<?php

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler;

echo '<div class="sk-addon-select sk-addon-'.$addon['type'].'" 
    '.ProductAddOnHandler::FORM_DATA_SK_ADDON_GROUP_ID.'="'.$addon['product_addon_group_id'].'">';

echo '<p class="sk-addon-title">'.esc_html($addon['title']).'</p>';
echo '<ul>';
foreach ($addon['options'] as $option) {
    echo '<li>'.esc_html($option['title']).'</li>';
}
echo '</ul>';
echo '</div>';
