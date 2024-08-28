<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;

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
    public const CART_FIELD_PRICE = self::FIELD_PREFIX.'_price';
    public const CART_FIELD_PARENT_ID = OrderExport::CART_FIELD_PARENT_ID;
    public const CART_FIELD_ID = OrderExport::CART_FIELD_ID;
    public const CART_FIELD_NAME = OrderExport::CART_FIELD_NAME;
    public const CART_FIELD_SHOP_PRODUCT_ID = OrderExport::CART_FIELD_SHOP_PRODUCT_ID;
    public const CART_FIELD_ADDON_GROUP_ID = OrderExport::CART_FIELD_ADDON_GROUP_ID;

    public const CART_FIELDS = [
        self::CART_FIELD_SELECTED_IDS,
        self::CART_FIELD_PRICE,
        self::CART_FIELD_PARENT_ID,
        self::CART_FIELD_ID,
        self::CART_FIELD_NAME,
        self::CART_FIELD_SHOP_PRODUCT_ID,
        self::CART_FIELD_ADDON_GROUP_ID,
    ];
    public const ADDON_SKU = '7718efcc-07fe-4027-b10a-8fdc6871e883';
    public const CSS_CLASS_ADDON_PRODUCT = 'sk-addon-product';
    public const CSS_CLASS_ADDON_SUBPRODUCT = 'sk-addon-subproduct';
    public const KEY_ORDERABLE_STOCK = 'orderable_stock';

    public function registerHooks(): void
    {
        add_action('wp_head', [$this, 'add_styles']);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'add_addon_fields']);
        add_action('woocommerce_before_add_to_cart_form', [$this, 'add_price_update_script']);
        add_action('woocommerce_add_to_cart', [$this, 'add_additional_item_to_cart'], 10, 6);
        add_action('woocommerce_before_calculate_totals', [$this, 'add_custom_price'], 10, 1);
        add_filter('woocommerce_add_cart_item_data', [$this, 'save_custom_field_data'], 10, 3);
        add_filter('woocommerce_cart_item_name', [$this, 'custom_cart_item_name'], 10, 3);
        add_filter('woocommerce_cart_item_class', [$this, 'add_subproduct_class'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_custom_field_data'], 10, 2);
        add_filter('woocommerce_cart_item_remove_link', [$this, 'remove_cart_item_remove_link'], 10, 2);
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'remove_cart_item_thumbnail'], 10, 3);
        add_filter('woocommerce_cart_item_quantity', [$this, 'remove_quantity_input_for_subproducts'], 10, 3);
        add_action('woocommerce_cart_item_removed', [$this, 'remove_subproducts_when_main_product_removed'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'modify_order_line_item'], 10, 4);
        add_action('woocommerce_after_cart_item_quantity_update', [$this, 'update_subproduct_quantity'], 10, 4);
        add_filter('woocommerce_order_item_name', [$this, 'order_item_component_name'], 10, 2);
        add_filter('woocommerce_cart_item_permalink', [$this, 'woocommerce_cart_item_permalink_filter'], 10, 3);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'hide_meta_for_display'], 10, 2);
    }

    public const PRODUCT_ADDONS = [
        [
            'product_addon_group_id' => 1,
            'title' => 'Standaard inbegrepen',
            'type' => self::ADDON_TYPE_REQUIRED_ADDON,
            'options' => [
                [
                    'id' => 1,
                    'title' => 'Zanddeeg 500 gram',
                    'ppu_wt' => 2.85,
                    'shop_product_id' => 135842,
                    self::KEY_ORDERABLE_STOCK => 5,
                ],
                [
                    'id' => 2,
                    'title' => 'Banketbakkersroom (bakvast) 100 gram (CaH)',
                    'ppu_wt' => 1.85,
                    'shop_product_id' => 135843,
                    self::KEY_ORDERABLE_STOCK => 99,
                ],
                [
                    'id' => 3,
                    'title' => 'Amandelspijs 100 gram',
                    'ppu_wt' => 2.35,
                    'shop_product_id' => 135844,
                ],
            ],
        ],
        [
            'product_addon_group_id' => 2,
            'title' => 'Smaak bavarois',
            'type' => self::ADDON_TYPE_SINGLE_CHOICE,
            'options' => [
                [
                    'id' => 4,
                    'title' => 'Bavarois Advocaat 100 gram',
                    'ppu_wt' => 3.75,
                    'shop_product_id' => 135837,
                    self::KEY_ORDERABLE_STOCK => 0,
                ],
                [
                    'id' => 5,
                    'title' => 'Bavarois Crème Brûlée',
                    'ppu_wt' => 3.00,
                    'shop_product_id' => 135837,
                    self::KEY_ORDERABLE_STOCK => 3,
                ],
                [
                    'id' => 6,
                    'title' => 'Bavarois Cappuccino 100 gram',
                    'ppu_wt' => 1.50,
                    'shop_product_id' => 135837,
                ],
            ],
        ],
        [
            'product_addon_group_id' => 4,
            'title' => 'Smaak bavarois (extra)',
            'type' => self::ADDON_TYPE_MULTIPLE_CHOICE,
            'options' => [
                [
                    'id' => 7,
                    'title' => 'Bavarois Banaan 100 gram',
                    'ppu_wt' => 2,
                    'shop_product_id' => 112711,
                    self::KEY_ORDERABLE_STOCK => 3,
                ],
                [
                    'id' => 8,
                    'title' => 'Bavarois Rabarber-Aardbei 100 gram',
                    'ppu_wt' => 5,
                    'shop_product_id' => 80156,
                    self::KEY_ORDERABLE_STOCK => 0,
                ],
                [
                    'id' => 9,
                    'title' => 'Bavarois Yoghurt-Kers 100 gram',
                    'ppu_wt' => 10,
                    'shop_product_id' => 4732,
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

        $product->set_name('StoreKeeper Not-configured Addon');
        $product->set_sku(self::ADDON_SKU);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_sold_individually(false); // todo?
        $product->set_price(0.00);
        $product->set_regular_price(0.00);
        $product->save();

        return $product->get_id();
    }

    public function hide_meta_for_display($formatted_meta, $order_item): array
    {
        $valid_meta = [];
        foreach ($formatted_meta as $id => $meta) {
            if (!in_array($meta->key, self::CART_FIELDS)) {
                $valid_meta[$id] = $meta;
            }
        }

        return $valid_meta;
    }

    public function add_addon_fields(): void
    {
        global $product;

        if (!($product instanceof \WC_Product)) {
            return;
        }
        if (!$this->isProductWithAddOns($product->get_id())) {
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
        if (!$this->isProductWithAddOns($product->get_id())) {
            return;
        }
        list($required_price, $price_addon_changes) = $this->calculateRequiredAndOptionalPriceChanges($product);

        $template_path = Core::plugin_abspath().'templates/';
        $price = $this->getProductSalePrice($product);

        wc_get_template('add-on/js/update-price.php', [
            'start_price' => $required_price + $price,
            'price_addon_changes' => $price_addon_changes,
        ], '', $template_path);
    }

    public function add_styles()
    {
        $template_path = Core::plugin_abspath().'templates/';
        wc_get_template('add-on/css/add-on-styles.php', [], '', $template_path);
    }

    public function save_custom_field_data($cart_item_data, $product_id, $variation_id)
    {
        if ($this->isProductWithAddOns($product_id)) {
            $has_add_ons = $this->hasRequiredAddOns($product_id);
            $cart_item_data[self::CART_FIELD_SELECTED_IDS] = [];
            if (
                isset($_POST[self::FIELD_CHOICE])
                && is_array($_POST[self::FIELD_CHOICE])
            ) {
                $cart_item_data[self::CART_FIELD_SELECTED_IDS] = $this->getSelectedOptionFromPost();
                $has_add_ons = true;
            }
            if ($has_add_ons) {
                $cart_item_data[self::CART_FIELD_ID] = uniqid();
            }
        }

        return $cart_item_data;
    }

    public function add_additional_item_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        if (
            !empty($cart_item_data[self::CART_FIELD_ID])
            && empty($cart_item_data[self::CART_FIELD_PARENT_ID]) // exclude children
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
                            self::CART_FIELD_PARENT_ID => $cart_item_data[self::CART_FIELD_ID],
                            self::CART_FIELD_PRICE => $option['ppu_wt'],
                            self::CART_FIELD_NAME => $option['title'],
                            self::CART_FIELD_SHOP_PRODUCT_ID => $option['shop_product_id'],
                            self::CART_FIELD_ADDON_GROUP_ID => $addon['product_addon_group_id'],
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
                isset($cart_item[self::CART_FIELD_PRICE])
                && isset($cart_item[self::CART_FIELD_PARENT_ID])
            ) {
                $cart_item['data']->set_price($cart_item[self::CART_FIELD_PRICE]);
            }
        }
    }

    public function custom_cart_item_name($name, $cart_item, $cart_item_key)
    {
        if (!empty($cart_item[self::CART_FIELD_NAME])) {
            return $cart_item[self::CART_FIELD_NAME];
        }

        return $name;
    }

    public function add_subproduct_class($class, $cart_item, $cart_item_key)
    {
        if (isset($cart_item[self::CART_FIELD_PARENT_ID])) {
            $class .= ' '.self::CSS_CLASS_ADDON_SUBPRODUCT;
        } elseif (isset($cart_item[self::CART_FIELD_ID])) {
            $class .= ' '.self::CSS_CLASS_ADDON_PRODUCT;
        }

        return $class;
    }

    public function display_custom_field_data($item_data, $cart_item)
    {
        return $item_data; // todo
    }

    public function remove_quantity_input_for_subproducts($quantity_input, $cart_item_key, $cart_item)
    {
        if (isset($cart_item[self::CART_FIELD_PARENT_ID])) {
            return '';
        }

        return $quantity_input;
    }

    public function remove_cart_item_remove_link($link, $cart_item_key)
    {
        $cart = WC()->cart->get_cart();
        $cart_item = $cart[$cart_item_key];
        if (isset($cart_item[self::CART_FIELD_PARENT_ID])) {
            return '';
        }

        return $link;
    }

    public function remove_cart_item_thumbnail($thumbnail, $cart_item, $cart_item_key)
    {
        if (isset($cart_item[self::CART_FIELD_PARENT_ID])) {
            return '';
        }

        return $thumbnail;
    }

    public function modify_order_line_item(\WC_Order_Item_Product $item, $cart_item_key, $cart_item_data, \WC_Order $order)
    {
        if (!empty($cart_item_data[self::CART_FIELD_ID])) {
            foreach (self::CART_FIELDS as $field_name) {
                if (isset($cart_item_data[$field_name])) {
                    $item->add_meta_data($field_name, $cart_item_data[$field_name]);
                }
            }
        }
    }

    public function woocommerce_cart_item_permalink_filter($product_permalink, $cart_item, $cart_item_key)
    {
        // needed to replace the name on the checkout order summary
        // other option would be replacing in js or new product type with some name getting from global
        if (isset($cart_item[self::CART_FIELD_NAME])) {
            if ($cart_item['data'] instanceof \WC_Product) {
                $cart_item['data']->set_name($cart_item[self::CART_FIELD_NAME]);
            }
        }

        return $product_permalink;
    }

    public function order_item_component_name($content, $order_item)
    {
        return $content; // todo for admin
    }

    protected function getSingleKeyName(int $id): string
    {
        return self::FIELD_CHOICE."[$id][".self::ADDON_TYPE_SINGLE_CHOICE.']';
    }

    protected function getMultipleChoiceKeyName(int $addon_id, int $option_id): string
    {
        return self::FIELD_CHOICE."[$addon_id][".self::ADDON_TYPE_MULTIPLE_CHOICE."][$option_id]";
    }

    protected function getAddOnsForProduct(\WC_Product $product): array
    {
        $shop_product_id = $product->get_meta('storekeeper_id');

        $result = [];
        foreach (self::PRODUCT_ADDONS as $addon) {
            $id = $addon['product_addon_group_id'];
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

    protected function calculateRequiredAndOptionalPriceChanges(\WC_Product $product): array
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

    public function remove_subproducts_when_main_product_removed($cart_item_key, $cart)
    {
        $removed_item = $cart->removed_cart_contents[$cart_item_key];

        if (isset($removed_item[self::CART_FIELD_ID])) {
            $main_product_addon_id = $removed_item[self::CART_FIELD_ID];

            foreach ($cart->cart_contents as $key => $cart_item) {
                if (
                    isset($cart_item[self::CART_FIELD_PARENT_ID])
                    && $cart_item[self::CART_FIELD_PARENT_ID] === $main_product_addon_id
                ) {
                    $cart->remove_cart_item($key);
                }
            }
        }
    }

    public function update_subproduct_quantity($cart_item_key, $new_quantity, $old_quantity = null, $cart = null)
    {
        if (null === $cart) {
            $cart = WC()->cart;
        }

        $cart_item = $cart->get_cart_item($cart_item_key);
        if (isset($cart_item[self::CART_FIELD_ID])) {
            $main_product_addon_id = $cart_item[self::CART_FIELD_ID];
            foreach ($cart->get_cart() as $key => $item) {
                if (
                    isset($item[self::CART_FIELD_PARENT_ID])
                    && $item[self::CART_FIELD_PARENT_ID] === $main_product_addon_id
                ) {
                    $cart->set_quantity($key, $new_quantity, false);
                }
            }
        }
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

    protected function getProductSalePrice(\WC_Product $product): float
    {
        $price = $product->get_sale_price('edit');
        if (empty($price)) {
            $price = $product->get_regular_price('edit');
        }
        $price = floatval($price);

        return $price;
    }

    protected function isProductWithAddOns(int $product_id): bool
    {
        return '1' === get_post_meta($product_id, ProductImport::META_HAS_ADDONS, true);
    }

    protected function hasRequiredAddOns(int $product_id): bool
    {
        if (!$this->isProductWithAddOns($product_id)) {
            return false;
        }

        return true; // todo
    }
}
