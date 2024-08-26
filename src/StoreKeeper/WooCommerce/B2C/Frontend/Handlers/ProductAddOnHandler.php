<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;

class ProductAddOnHandler implements WithHooksInterface
{

    public function registerHooks(): void
    {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'add_addon_fields']);
        add_action('woocommerce_add_to_cart_validation', [$this, 'validate_custom_field'], 10, 3);
        add_filter('woocommerce_add_cart_item_data',  [$this, 'save_custom_field_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this,'display_custom_field_data'], 10, 2);

    }


    public function add_addon_fields(): void
    {
        global $product;

        if (!$product) {
            return;
        }

        // todo check if add-on product

        // todo single multiselects
        woocommerce_form_field('custom_option', array(
            'type' => 'select',
            'class' => array('form-row-wide'),
            'label' => __('Custom Option'),
            'required' => true,
            'options' => array(
                '' => 'Please select',
                'option1' => 'Product 1 (+1.00)',
                'option2' => 'Product 3 (+1.00)'
            )
        ));
        // todo render multiselects
        echo '<p class="custom-checkbox-description">Extra addons for the cake:</p>';
        woocommerce_form_field('custom_checkbox_1', array(
            'type' => 'checkbox',
            'class' => array('form-row-wide'),
            'label' => __('Product 1 (+1.00)'),
        ));
        woocommerce_form_field('custom_checkbox_2', array(
            'type' => 'checkbox',
            'class' => array('form-row-wide'),
            'label' => __('Product 2 (+1.00)'),
        ));
    }
    function validate_custom_field($passed, $product_id, $quantity) {
        if (empty($_POST['custom_option'])) {
            wc_add_notice(__('Please select a custom option before adding this product to your cart.'), 'error');
            $passed = false;
        }
        return $passed;
    }
    function save_custom_field_data($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['custom_option'])) {
            $cart_item_data['custom_option'] = sanitize_text_field($_POST['custom_option']);
        }
        if (isset($_POST['custom_checkbox_1'])) {
            $cart_item_data['custom_checkbox_1'] = sanitize_text_field($_POST['custom_checkbox_1']);
        }
        if (isset($_POST['custom_checkbox_2'])) {
            $cart_item_data['custom_checkbox_2'] = sanitize_text_field($_POST['custom_checkbox_2']);
        }
        return $cart_item_data;
    }
    function display_custom_field_data($item_data, $cart_item) {
        if (isset($cart_item['custom_option'])) {
            $item_data[] = array(
                'key' => __('Custom Option'),
                'value' => wc_clean($cart_item['custom_option'])
            );
        }
        return $item_data;
    }
}
