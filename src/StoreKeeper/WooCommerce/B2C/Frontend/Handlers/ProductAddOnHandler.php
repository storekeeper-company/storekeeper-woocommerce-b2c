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
    public const FIELD_PREFIX = 'sk_add_on';
    public const FIELD_CHOICE = self::FIELD_PREFIX.'_choice_select';
    public const KEY_FORM_ID = 'form_id';
    public const KEY_FORM_OPTIONS = 'form_options';
    public const INPUT_TYPE_PRODUCT_ADD_ON = 'product-add-on';
    public const CART_FIELD_SELECTED_IDS = self::FIELD_PREFIX.'_selected_ids';
    public const CART_FIELD_ADDON_PRICE = self::FIELD_PREFIX.'_price';
    public const CART_FIELD_ADDON_PARENT = self::FIELD_PREFIX.'_parent';
    public const CART_FIELD_ADDON_ID = self::FIELD_PREFIX.'_id';
    public const CART_FIELD_ADDON_NAME = self::FIELD_PREFIX.'_name';
    public const CART_FIELD_ADDON_DATA = self::FIELD_PREFIX.'_data';
    public const ADDON_SKU = '7718efcc-07fe-4027-b10a-8fdc6871e882';

    public function registerHooks(): void
    {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'add_addon_fields']);
        add_action('woocommerce_before_add_to_cart_form', [$this, 'add_price_update_script']);
        add_action('woocommerce_add_to_cart_validation', [$this, 'validate_custom_field'], 10, 3);
        add_action('woocommerce_add_to_cart', [$this, 'add_additional_item_to_cart'], 10, 6);
        add_action('woocommerce_before_calculate_totals', [$this, 'add_custom_price'], 10, 1);
        add_filter('woocommerce_add_cart_item_data', [$this, 'save_custom_field_data'], 10, 3);
        add_filter('woocommerce_cart_item_name', [$this, 'custom_cart_item_name'], 10, 3);
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

    public function getSkAddonProductId(): int
    {
        $product_id = wc_get_product_id_by_sku(self::ADDON_SKU);
        if ($product_id) {
            return $product_id; // todo cache me
        }
        $product = new \WC_Product_Simple();

        $product->set_name('StoreKeeper Addon Product');
        $product->set_sku(self::ADDON_SKU);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_price(0.00);
        $product->set_regular_price(0.00);
        $product->set_description('This is a hidden product for internal use.');
        $product->save();

        return $product->get_id();
    }

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
                // todo sum up the required add ons
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

    public function add_price_update_script()
    {
        global $product;

        if (!$product) {
            return;
        }
        list($required_price, $price_addon_changes) = $this->calculateStartPriceAndAddOnChanges($product);

        $template_path = Core::plugin_abspath().'templates/';
        $price = $this->getProductSalePrice($product);

        wc_get_template('add-on/js/update-price.php', [
            'start_price' => $required_price + $price,
            'price_addon_changes' => $price_addon_changes,
        ], '', $template_path);
    }

    public function validate_custom_field($passed, $product_id, $quantity)
    {
        // todo validate if required are added, change to auto change items
        //        if (empty($_POST['custom_option'])) {
        //            wc_add_notice(__('Please select a custom option before adding this product to your cart.'), 'error');
        //            $passed = false;
        //        }

        return $passed;
    }

    public function save_custom_field_data($cart_item_data, $product_id, $variation_id)
    {
        if ($this->isProductWithAddOns($product_id)) {
            $cart_item_data[self::CART_FIELD_ADDON_ID] = uniqid();
            $cart_item_data[self::CART_FIELD_SELECTED_IDS] = [];

            if (
                isset($_POST[self::FIELD_CHOICE])
                && is_array($_POST[self::FIELD_CHOICE])
            ) {
                $cart_item_data[self::CART_FIELD_SELECTED_IDS] = $this->getSelectedOptionFromPost();
            }
        }

        return $cart_item_data;
    }

    public function add_additional_item_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        if (
            !empty($cart_item_data[self::CART_FIELD_ADDON_ID])
            && empty($cart_item_data[self::CART_FIELD_ADDON_PARENT]) // exclude children
        ) {
            $addon_id = $this->getSkAddonProductId();
            $product = wc_get_product($product_id);

            $all_selected_option_ids = $cart_item_data[self::CART_FIELD_SELECTED_IDS];
            foreach ($this->getAddOnsForProduct($product) as $addon) {
                $type = $addon['type'];
                foreach ($addon['options'] as $option) {
                    $selected = self::ADDON_TYPE_REQUIRED_ADDON === $type
                        || self::ADDON_TYPE_BUNDLE === $type
                        || in_array($option['id'], $all_selected_option_ids)
                    ;
                    if ($selected) {
                        $addon_item_data = [
                            self::CART_FIELD_ADDON_PARENT => $cart_item_data[self::CART_FIELD_ADDON_ID],
                            self::CART_FIELD_ADDON_PRICE => $option['ppu_wt'],
                            self::CART_FIELD_ADDON_NAME => $option['title'],
                        ];
                        WC()->cart->add_to_cart(
                            $addon_id,
                            $quantity,
                            0, // $variation_id
                            [], // $variation
                            $addon_item_data
                        );
                    }
                }
            }
        }
    }

    public function add_custom_price($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (
                isset($cart_item[self::CART_FIELD_ADDON_PRICE])
                && isset($cart_item[self::CART_FIELD_ADDON_PARENT])
            ) {
                $cart_item['data']->set_price($cart_item[self::CART_FIELD_ADDON_PRICE]);
            }
        }
    }

    public function custom_cart_item_name($name, $cart_item, $cart_item_key)
    {
        if (!empty($cart_item[self::CART_FIELD_ADDON_NAME])) {
            return $cart_item[self::CART_FIELD_ADDON_NAME];
        }

        return $name;
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
        return self::FIELD_CHOICE."[$id][".self::ADDON_TYPE_SINGLE_CHOICE.']';
    }

    protected function getMultipleChoiceKeyName($addon_id, $option_id): string
    {
        return self::FIELD_CHOICE."[$addon_id][".self::ADDON_TYPE_MULTIPLE_CHOICE."][$option_id]";
    }

    protected function getAddOnsForProduct(\WC_Product $product): array
    {
        $result = [];
        foreach (self::PRODUCT_ADDONS as $addon) {
            $id = $addon['addon_group_id'];
            $type = $addon['type'];
            if (self::ADDON_TYPE_SINGLE_CHOICE === $type) {
                $field_options = [
                    '' => 'Choose option', // todo localize
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
                        'data-sk-type' => self::INPUT_TYPE_PRODUCT_ADD_ON,
                    ],
                ];
            } elseif (self::ADDON_TYPE_MULTIPLE_CHOICE === $type) {
                foreach ($addon['options'] as &$option) {
                    $option[self::KEY_FORM_ID] = $this->getMultipleChoiceKeyName($id, $option['id']);
                    $option[self::KEY_FORM_OPTIONS] = [
                        'type' => 'checkbox',
                        'class' => ['form-row-wide'],
                        'label' => $this->formatOptionTitle($option),
                        'checked_value' => $option['id'],
                        'custom_attributes' => [
                            'data-sk-type' => self::INPUT_TYPE_PRODUCT_ADD_ON,
                        ],
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
        return $option['title'].' (+'.$option['ppu_wt'].')';
    }

    protected function calculateStartPriceAndAddOnChanges(\WC_Product $product): array
    {
        $required_price = 0;
        $price_addon_changes = [];
        foreach ($this->getAddOnsForProduct($product) as $addon) {
            $type = $addon['type'];
            if (self::ADDON_TYPE_REQUIRED_ADDON === $type || self::ADDON_TYPE_BUNDLE === $type) {
                foreach ($addon['options'] as $option) {
                    $required_price += $option['ppu_wt'];
                }
            } elseif (self::ADDON_TYPE_SINGLE_CHOICE === $type || self::ADDON_TYPE_MULTIPLE_CHOICE === $type) {
                foreach ($addon['options'] as $option) {
                    $price_addon_changes[$option['id']] = $option['ppu_wt'];
                }
            }
        }

        return [$required_price, $price_addon_changes];
    }

    protected function getSelectedOptionFromPost(): array
    {
        $all_selected_option_ids = [];
        foreach ($_POST[self::FIELD_CHOICE] as $choice) {
            $single = &$choice[self::ADDON_TYPE_SINGLE_CHOICE];
            if (isset($single)) {
                $all_selected_option_ids[] = intval($single);
            }
            $multiple = &$choice[self::ADDON_TYPE_MULTIPLE_CHOICE];
            if (isset($multiple) && is_array($multiple)) {
                foreach ($multiple as $option_id) {
                    $all_selected_option_ids[] = intval($option_id);
                }
            }
        }
        $all_selected_option_ids = array_filter($all_selected_option_ids);

        return $all_selected_option_ids;
    }

    protected function getProductSalePrice($product): float
    {
        $price = $product->get_sale_price('edit');
        if (empty($price)) {
            $price = $product->get_regular_price('edit');
        }
        $price = floatval($price);

        return $price;
    }

    protected function isProductWithAddOns($product_id): bool
    {
        $is_with_add_ons = false;
        if ($product_id) {
            $is_with_add_ons = true; // todo
        }

        return $is_with_add_ons;
    }
}
