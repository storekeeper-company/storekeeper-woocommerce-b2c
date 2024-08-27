<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;

class ProductAddOnHandler implements WithHooksInterface
{
    public const ADDON_TYPE_SINGLE_CHOICE = 'single-choice';
    public const ADDON_TYPE_MULTIPLE_CHOICE = 'multiple-choice';
    public const ADDON_TYPE_REQUIRED_ADDON = 'required-addon';
    public const ADDON_TYPE_BUNDLE = 'bundle';
    public const FIELD_PREFIX = 'sk_add_on_choice';
    public const FIELD_SELECT = self::FIELD_PREFIX . '_choice_select';
    const KEY_FORM_ID = 'form_id';
    const KEY_FORM_OPTIONS = 'form_options';
    const INPUT_TYPE_PRODUCT_ADD_ON = 'product-add-on';

    public function registerHooks(): void
    {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'add_addon_fields']);
        add_action('woocommerce_before_add_to_cart_form', [$this,'add_price_update_script']);
        add_action('woocommerce_add_to_cart_validation', [$this, 'validate_custom_field'], 10, 3);
        add_filter('woocommerce_add_cart_item_data', [$this, 'save_custom_field_data'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_custom_field_data'], 10, 2);
    }

    public const PRODUCT_ADDONS = [
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
                ],
            ],
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
                    'ppu_wt' => 3.00,
                    'shop_product_id' => 123,
                ],
                [
                    'id' => 6,
                    'title' => 'Bavarois Cappuccino 100 gram',
                    'ppu_wt' => 1.50,
                    'shop_product_id' => 123,
                ],
            ],
        ],
        [
            'addon_group_id' => 4,
            'title' => 'Smaak bavarois (extra)',
            'type' => self::ADDON_TYPE_MULTIPLE_CHOICE,
            'options' => [
                [
                    'id' => 7,
                    'title' => 'Bavarois Banaan 100 gram',
                    'ppu_wt' => 2,
                    'shop_product_id' => 123,
                ],
                [
                    'id' => 8,
                    'title' => 'Bavarois Rabarber-Aardbei 100 gram',
                    'ppu_wt' => 5,
                    'shop_product_id' => 123,
                ],
                [
                    'id' => 9,
                    'title' => 'Bavarois Yoghurt-Kers 100 gram',
                    'ppu_wt' => 10,
                    'shop_product_id' => 123,
                ],
            ],
        ],
    ];

    public function add_addon_fields(): void
    {
        global $product;

        if (!$product) {
            return;
        }
        foreach ($this->getAddOnsForProduct($product) as $addon) {
            $type = $addon['type'];
            // todo disable out of stock
            if (self::ADDON_TYPE_REQUIRED_ADDON === $type || self::ADDON_TYPE_BUNDLE === $type) {
                echo '<p class="custom-checkbox-description">'.$addon['title'].'</p>'; // todo style better
                echo '<ul>';
                foreach ($addon['options'] as $option) {
                    echo '<li>'.$option['title'].'</li>';
                }
                echo '</ul>';
            } elseif (self::ADDON_TYPE_SINGLE_CHOICE === $type) {
                woocommerce_form_field($addon[self::KEY_FORM_ID], $addon[self::KEY_FORM_OPTIONS]);
            } elseif (self::ADDON_TYPE_MULTIPLE_CHOICE === $type) {
                echo '<p class="custom-checkbox-description">'.$addon['title'].'</p>';
                foreach ($addon['options'] as $option) {
                    woocommerce_form_field($option[self::KEY_FORM_ID], $option[self::KEY_FORM_OPTIONS]);
                }
            }
        }
    }

    function add_price_update_script() {
        global $product;

        if (!$product) {
            return;
        }
        list($start_price, $price_addon_changes) = $this->calculateStartPriceAndAddOnChanges($product);

        $template_path = Core::plugin_abspath() . 'templates/';

        wc_get_template( 'add-on/js/update-price.php', array(
            'start_price'           => $start_price,
            'price_addon_changes'   => $price_addon_changes,
        ), '', $template_path);
    }
    public function validate_custom_field($passed, $product_id, $quantity)
    {
        // todo validate if required are added, change to auto change items
        if (empty($_POST['custom_option'])) {
            wc_add_notice(__('Please select a custom option before adding this product to your cart.'), 'error');
            $passed = false;
        }

        return $passed;
    }

    public function save_custom_field_data($cart_item_data, $product_id, $variation_id)
    {
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

    public function display_custom_field_data($item_data, $cart_item)
    {
        if (isset($cart_item['custom_option'])) {
            $item_data[] = [
                'key' => __('Custom Option'),
                'value' => wc_clean($cart_item['custom_option']),
            ];
        }

        return $item_data;
    }

    protected function getSingleKeyName($id): string
    {
        return self::FIELD_SELECT."[$id][".self::ADDON_TYPE_SINGLE_CHOICE.']';
    }

    protected function getMultipleChoiceKeyName($addon_id, $option_id): string
    {
        return self::FIELD_SELECT."[$addon_id][".self::ADDON_TYPE_MULTIPLE_CHOICE."][$option_id]";
    }

    protected function getAddOnsForProduct(\WC_Product $product): array
    {
        $result = [];
        foreach (self::PRODUCT_ADDONS as $addon) {
            $id = $addon['addon_group_id'];
            $type = $addon['type'];
            if (self::ADDON_TYPE_SINGLE_CHOICE === $type) {
                $field_options = [
                    '' => 'Choose option' // todo localize
                ];
                foreach ($addon['options'] as &$option) {
                    $field_options[$option['id']] = $this->formatOptionTitle($option);
                }
                $addon[self::KEY_FORM_ID] = $this->getSingleKeyName($id);
                $addon[self::KEY_FORM_OPTIONS] = [
                    'type' => 'select',
                    'class' => ['form-row-wide'],
                    'label' => $addon['title'],
                    'required' => false,
                    'options' => $field_options,
                    'custom_attributes' => [
                        'data-sk-type' => self::INPUT_TYPE_PRODUCT_ADD_ON
                    ]
                ];
            } elseif (self::ADDON_TYPE_MULTIPLE_CHOICE === $type) {
                foreach ($addon['options'] as &$option) {
                    $option[self::KEY_FORM_ID] = $this->getMultipleChoiceKeyName($id, $option['id']);
                    $option[self::KEY_FORM_OPTIONS] = [
                        'type' => 'checkbox',
                        'class' => ['form-row-wide'],
                        'label' => $this->formatOptionTitle($option),
                        'checked_value' => $option['id'],
                        'unchecked_value' => 0,
                        'custom_attributes' => [
                            'data-sk-type' => self::INPUT_TYPE_PRODUCT_ADD_ON
                        ]
                    ];
                }
            }
            $result[] = $addon;
        }
        return $result;
    }

    protected function formatOptionTitle($option): string
    {
        // todo format price
        return $option['title'] . ' (+' . $option['ppu_wt'] . ')';
    }

    protected function calculateStartPriceAndAddOnChanges($product): array
    {
        $start_price = $product->get_price();
        $price_addon_changes = [];
        foreach ($this->getAddOnsForProduct($product) as $addon) {
            $type = $addon['type'];
            if (self::ADDON_TYPE_REQUIRED_ADDON === $type || self::ADDON_TYPE_BUNDLE === $type) {
                foreach ($addon['options'] as $option) {
                    $start_price += $option['ppu_wt'];
                }
            } elseif (self::ADDON_TYPE_SINGLE_CHOICE === $type || self::ADDON_TYPE_MULTIPLE_CHOICE === $type) {
                foreach ($addon['options'] as $option) {
                    $price_addon_changes[$option['id']] = $option['ppu_wt'];
                }
            }
        }
        return [$start_price, $price_addon_changes];
    }
}
