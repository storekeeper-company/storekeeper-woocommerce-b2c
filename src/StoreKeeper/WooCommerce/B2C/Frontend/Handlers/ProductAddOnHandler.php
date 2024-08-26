<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;

class ProductAddOnHandler implements WithHooksInterface
{
    public const ADDON_TYPE_SINGLE_CHOICE = 'single-choice';
    public const ADDON_TYPE_MULTIPLE_CHOICE = 'multiple-choice';
    public const ADDON_TYPE_REQUIRED_ADDON = 'required-addon';
    public const ADDON_TYPE_BUNDLE = 'bundle'; // todo
    const FIELD_PREFIX = 'sk_add_on_choice';

    public function registerHooks(): void
    {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'add_addon_fields']);
        add_action('woocommerce_add_to_cart_validation', [$this, 'validate_custom_field'], 10, 3);
        add_filter('woocommerce_add_cart_item_data',  [$this, 'save_custom_field_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this,'display_custom_field_data'], 10, 2);

    }

    const PRODUCT_ADDONS = [
        [
            'addon_group_id' => 1,
            'title' => 'Standaard inbegrepen',
            'type' => self::ADDON_TYPE_REQUIRED_ADDON,
            'options' => [
                [
                    'id' => 1,
                    'title' => 'Zanddeeg 500 gram',
                    'ppu_wt' => 2.85,
                    'shop_product_id' => 123,
                ],
                [
                    'id' => 2,
                    'title' => 'Banketbakkersroom (bakvast) 100 gram (CaH)',
                    'ppu_wt' => 1.85,
                    'shop_product_id' => 123,
                ],
                [
                    'id' => 3,
                    'title' => 'Amandelspijs 100 gram',
                    'ppu_wt' => 2.35,
                    'shop_product_id' => 122,
                ]
            ]
        ],
        [
            'addon_group_id' => 2,
            'title' => 'Smaak bavarois',
            'type' => self::ADDON_TYPE_SINGLE_CHOICE,
            'options' => [
                [
                    'id' => 4,
                    'title' => 'Bavarois Advocaat 100 gram',
                    'ppu_wt' => 3.75,
                    'shop_product_id' => 123,
                ],
                [
                    'id' => 5,
                    'title' => 'Bavarois Crème Brûlée',
                    'ppu_wt' => 3.75,
                    'shop_product_id' => 123,
                ],
                [
                    'id' => 6,
                    'title' => 'Bavarois Cappuccino 100 gram',
                    'ppu_wt' => 3.75,
                    'shop_product_id' => 123,
                ],
            ]
        ],
        [
            'addon_group_id' => 4,
            'title' => 'Smaak bavarois (extra)',
            'type' => self::ADDON_TYPE_MULTIPLE_CHOICE,
            'options' => [
                [
                    'id' => 7,
                    'title' => 'Bavarois Banaan 100 gram',
                    'ppu_wt' => 3.75,
                    'shop_product_id' => 123,
                ],
                [
                    'id' => 8,
                    'title' => 'Bavarois Rabarber-Aardbei 100 gram',
                    'ppu_wt' => 3.75,
                    'shop_product_id' => 123,
                ],
                [
                    'id' => 9,
                    'title' => 'Bavarois Yoghurt-Kers 100 gram',
                    'ppu_wt' => 3.75,
                    'shop_product_id' => 123,
                ],
            ]
        ]
    ];

    public function add_addon_fields(): void
    {
        global $product;

        if (!$product) {
            return;
        }

        foreach (self::PRODUCT_ADDONS as $addon) {
            $id = $addon['addon_group_id'];
            if( $addon['type'] === self::ADDON_TYPE_REQUIRED_ADDON ) {
                echo '<p class="custom-checkbox-description">'.$addon['title'].'</p>'; // todo style better
                echo '<ul>';
                foreach ($addon['options'] as $option) {
                    echo '<li>'.$option['title'].'</li>';
                }
                echo '</ul>';
            } else if( $addon['type'] === self::ADDON_TYPE_SINGLE_CHOICE ) {
                $field_options = [];
                foreach ($addon['options'] as $option) {
                    $field_options[$option['id']] =  $option['title'] . ' (+' . $option['ppu_wt'] . ')';
                }
                woocommerce_form_field($this->getSingleKeyName($id), array(
                    'type' => 'select',
                    'class' => array('form-row-wide'),
                    'label' => $addon['title'],
                    'required' => true,
                    'options' => $field_options
                ));
            } else if( $addon['type'] === self::ADDON_TYPE_MULTIPLE_CHOICE ) {
                echo '<p class="custom-checkbox-description">'.$addon['title'].'</p>';
                foreach ($addon['options'] as $option) {
                    woocommerce_form_field($this->getMultipleChoiceKeyName($id, $option['id']), array(
                        'type' => 'checkbox',
                        'class' => array('form-row-wide'),
                        'label' => $option['title'] . ' (+' . $option['ppu_wt'] . ')',
                    ));
                }

            }
        }

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

    protected function getSingleKeyName($id): string
    {
        return self::FIELD_PREFIX ."[$id][".self::ADDON_TYPE_SINGLE_CHOICE."]";
    }

    protected function getMultipleChoiceKeyName($addon_id, $option_id): string
    {
        return self::FIELD_PREFIX ."[$addon_id][".self::ADDON_TYPE_MULTIPLE_CHOICE."][$option_id]";
    }
}
