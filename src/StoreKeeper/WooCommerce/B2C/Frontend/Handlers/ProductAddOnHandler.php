<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

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
    public const INPUT_TYPE_PRODUCT_ADD_ON_SELECTOR = '[data-sk-type="'.self::INPUT_TYPE_PRODUCT_ADD_ON.'"]';
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
    public const CSS_CLASS_ADDON_PRODUCT = 'sk-addon-product';
    public const CSS_CLASS_ADDON_SUBPRODUCT = 'sk-addon-subproduct';
    public const KEY_WC_PRODUCT = 'wc_product';
    public const KEY_OUT_OF_STOCK_OPTION_IDS = 'out_of_stock_option_ids';
    public const OPTION_TITLE = 'option_title';
    public const FORM_DATA_SK_TYPE = 'data-sk-type';
    public const FORM_DATA_SK_ADDON = 'data-sk-addon';

    protected $addon_call_cache = [];

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
        add_filter('woocommerce_cart_item_quantity', [$this, 'remove_quantity_input_for_subproducts'], 10, 3);
        add_action('woocommerce_cart_item_removed', [$this, 'remove_subproducts_when_main_product_removed'], 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'modify_order_line_item'], 10, 4);
        add_action('woocommerce_after_cart_item_quantity_update', [$this, 'update_cart_subitem_quantity'], 10, 4);
        add_filter('woocommerce_order_item_name', [$this, 'order_item_component_name'], 10, 2);
        add_filter('woocommerce_cart_item_permalink', [$this, 'woocommerce_cart_item_permalink_filter'], 10, 3);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'hide_meta_for_display'], 10, 2);
        add_filter('woocommerce_update_cart_validation', [$this, 'validate_on_qty_on_update_cart_quantity'], 10, 4);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_on_add_to_cart'], 10, 5);
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

        $addons = $this->getAddOnsForProduct($product);
        $template_path = Core::plugin_abspath().'templates/';

        foreach ($addons as $addon) {
            $type = $addon['type'];
            if ($this->isRequiredType($type)) {
                wc_get_template('add-on/form-required.php', [
                    'addon' => $addon,
                ], '', $template_path);
            } elseif (self::ADDON_TYPE_SINGLE_CHOICE === $type) {
                wc_get_template('add-on/form-single-choice.php', [
                    'addon' => $addon,
                ], '', $template_path);
            } elseif (self::ADDON_TYPE_MULTIPLE_CHOICE === $type) {
                wc_get_template('add-on/form-multiple-choice.php', [
                    'addon' => $addon,
                ], '', $template_path);
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
            $product = wc_get_product($product_id);

            $all_selected_option_ids = $cart_item_data[self::CART_FIELD_SELECTED_IDS];
            foreach ($this->getAddOnsForProduct($product) as $addon) {
                $type = $addon['type'];
                foreach ($addon['options'] as $option) {
                    $selected = $this->isRequiredType($type)
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
                            $option[self::KEY_WC_PRODUCT]->get_id(),
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

    protected function getAddOnsFromApiWithWcProducts(\WC_Product $product): array
    {
        $shop_product_id = $product->get_meta('storekeeper_id');
        if (empty($shop_product_id)) {
            return [];
        }

        if (!array_key_exists($shop_product_id, $this->addon_call_cache)) {
            $addons = $this->getAddOnsFromApi($shop_product_id);
            $addons = $this->addWcProductsToAddons($addons);
            $addons = $this->filterAddonsWithWcProducts($addons);

            $this->addon_call_cache[$shop_product_id] = $addons;
        }

        return $this->addon_call_cache[$shop_product_id];
    }

    protected function getAddOnsFromApi(int $shop_product_id): array
    {
        try {
            $api = StoreKeeperApi::getApiByAuthName();
            $ShopModule = $api->getModule('ShopModule');
            $addon_groups = $ShopModule->getShopProductAddonIdsForHook([$shop_product_id]);
            if (empty($addon_groups)) {
                return [];
            }
            $addon_group = array_pop($addon_groups);
            $product_addon_group_ids = $addon_group['product_addon_group_ids'];
            if (empty($product_addon_group_ids)) {
                return [];
            }

            $formatted_addons = [];
            foreach ($product_addon_group_ids as $product_addon_group_id) {
                $group = $ShopModule->getShopProductAddonGroup($product_addon_group_id);

                $formatted_addon = [
                    'product_addon_group_id' => $product_addon_group_id,
                    'title' => $group['product_addon_group']['title'],
                    'type' => $group['product_addon_group']['type'],
                    'options' => [],
                ];

                foreach ($group['shop_product_addon_items'] as $addon_item) {
                    if ($addon_item['active']) {
                        $formatted_addon['options'][] = [
                            'id' => $addon_item['product_addon_item_id'],
                            'title' => $addon_item['title'],
                            'ppu_wt' => $addon_item['product_price']['ppu_wt'],
                            'shop_product_id' => $addon_item['shop_product_id'],
                        ];
                    }
                }

                $formatted_addons[] = $formatted_addon;
            }

            return $formatted_addons;
        } catch (\Exception $e) {
            LoggerFactory::create('load_errors')->error(
                'Shop product add ons cannot be loaded:  '.$e->getMessage(),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
        }

        return [];
    }

    protected function getAddOnsForProduct(\WC_Product $product): array
    {
        $addons = $this->getAddOnsFromApiWithWcProducts($product);

        /* @var \WC_Product $wc_product */
        $result = [];
        foreach ($addons as $addon) {
            $id = $addon['product_addon_group_id'];
            $type = $addon['type'];
            $out_of_stock_options = [];
            unset($option);
            foreach ($addon['options'] as &$option) {
                $option[self::KEY_FORM_ID] = $this->getMultipleChoiceKeyName($id, $option['id']);
                $option[self::OPTION_TITLE] = $this->formatOptionTitle($option);

                $wc_product = $option[self::KEY_WC_PRODUCT];
                if (!$wc_product->is_in_stock()) {
                    $out_of_stock_options[] = (int) $option['id'];
                }
            }
            $addon[self::KEY_FORM_ID] = $this->getSingleKeyName($id);
            $addon[self::KEY_OUT_OF_STOCK_OPTION_IDS] = $out_of_stock_options;

            $attributes = [
                self::FORM_DATA_SK_TYPE => self::INPUT_TYPE_PRODUCT_ADD_ON,
                self::FORM_DATA_SK_ADDON => $addon[self::KEY_FORM_ID],
            ];
            if (self::ADDON_TYPE_SINGLE_CHOICE === $type) {
                $field_options = [
                    '' => 'Choose option', // todo localize
                ];
                unset($option);
                foreach ($addon['options'] as &$option) {
                    $field_options[$option['id']] = $option[self::OPTION_TITLE];
                }
                $addon[self::KEY_FORM_OPTIONS] = [
                    'type' => 'select',
                    'class' => ['form-row-wide'],
                    'label' => $addon['title'],
                    'required' => false,
                    'options' => $field_options,
                    'custom_attributes' => $attributes,
                ];
            } elseif (self::ADDON_TYPE_MULTIPLE_CHOICE === $type) {
                unset($option);
                foreach ($addon['options'] as &$option) {
                    $option[self::KEY_FORM_OPTIONS] = [
                        'type' => 'checkbox',
                        'class' => ['form-row-wide'],
                        'label' => $option[self::OPTION_TITLE],
                        'checked_value' => $option['id'],
                        'custom_attributes' => $attributes,
                    ];
                }
            }
            $result[] = $addon;
        }

        return $result;
    }

    protected function formatOptionTitle($option): string
    {
        $price = strip_tags(wc_price($option['ppu_wt']));

        return $option['title'].' (+'.$price.')';
    }

    protected function calculateRequiredAndOptionalPriceChanges(\WC_Product $product): array
    {
        $required_price = 0;
        $price_addon_changes = [];
        foreach ($this->getAddOnsForProduct($product) as $addon) {
            $type = $addon['type'];
            if ($this->isRequiredType($type)) {
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

    public function validate_on_qty_on_update_cart_quantity($passed, $cart_item_key, $cart_item, $quantity)
    {
        if (isset($cart_item[self::CART_FIELD_ID])) {
            $set_quantities = [];
            $main_product_addon_id = $cart_item[self::CART_FIELD_ID];
            $cart = WC()->cart;
            foreach ($cart->get_cart() as $key => $item) {
                if (
                    isset($item[self::CART_FIELD_PARENT_ID])
                    && $item[self::CART_FIELD_PARENT_ID] === $main_product_addon_id
                ) {
                    $notice = $this->validateAddonNewCartQuantity($item, $cart, $set_quantities, $quantity);
                    if (!is_null($notice)) {
                        $passed = false;
                        wc_add_notice($notice, 'error');
                    }
                    $set_quantities[$key] = $quantity;
                }
            }
        }

        return $passed;
    }

    public function validate_on_add_to_cart($passed, $product_id, $quantity, $variation_id = '', $variations = '')
    {
        $cart_item = $this->save_custom_field_data([], $product_id, $variation_id);
        if (isset($cart_item[self::CART_FIELD_ID])) {
            $all_selected_option_ids = $cart_item[self::CART_FIELD_SELECTED_IDS];
            $cart = WC()->cart;
            $product = wc_get_product($product_id);

            $set_product_quantities = [];
            $send_errors = [];
            foreach ($this->getAddOnsForProduct($product) as $addon) {
                $type = $addon['type'];
                foreach ($addon['options'] as $option) {
                    $selected = $this->isRequiredType($type)
                        || in_array($option['id'], $all_selected_option_ids)
                    ;
                    if ($selected) {
                        $product_id = $option[self::KEY_WC_PRODUCT]->get_id();
                        if (!isset($set_product_quantities[$product_id])) {
                            $set_product_quantities[$product_id] = 0;
                        }
                        $set_product_quantities[$product_id] += $quantity;

                        $notice = $this->validateAddonNewCartQuantity(
                            [
                                'data' => $option[self::KEY_WC_PRODUCT],
                                'key' => uniqid(),
                            ],
                            $cart, [], $set_product_quantities[$product_id]
                        );
                        if (!is_null($notice)) {
                            $passed = false;
                            if (!in_array($product_id, $send_errors)) {
                                wc_add_notice($notice, 'error');
                                $send_errors[] = $product_id;
                            }
                        }
                    }
                }
            }
        }

        return $passed;
    }

    public function update_cart_subitem_quantity($cart_item_key, $new_quantity, $old_quantity = null, $cart = null)
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

        $product = wc_get_product($product_id);
        $addons = $this->getAddOnsForProduct($product);
        foreach ($addons as $addon) {
            if ($this->isRequiredType($addon['type']) && !empty($addon['options'])) {
                return true;
            }
        }

        return false;
    }

    protected function isRequiredType(string $type): bool
    {
        return self::ADDON_TYPE_REQUIRED_ADDON === $type || self::ADDON_TYPE_BUNDLE === $type;
    }

    protected function addWcProductsToAddons(array $addons): array
    {
        $shop_product_ids = [];
        foreach ($addons as $addon) {
            foreach ($addon['options'] as $option) {
                $shop_product_ids[] = $option['shop_product_id'];
            }
        }

        $products = wc_get_products([
            'limit' => -1,
            'meta_key' => 'storekeeper_id',
            'meta_value' => $shop_product_ids,
            'meta_compare' => 'IN',
        ]);
        $wc_product_per_id = [];
        foreach ($products as $product) {
            /* @var \WC_Product $product */
            $shop_product_id = $product->get_meta('storekeeper_id');
            $wc_product_per_id[$shop_product_id] = $product;
        }

        unset($addon, $option);
        foreach ($addons as &$addon) {
            foreach ($addon['options'] as &$option) {
                $shop_product_id = $option['shop_product_id'];
                if (array_key_exists($shop_product_id, $wc_product_per_id)) {
                    $option[self::KEY_WC_PRODUCT] = $wc_product_per_id[$shop_product_id];
                }
            }
        }

        return $addons;
    }

    protected function filterAddonsWithWcProducts(array $addons): array
    {
        $addon_with_wc_products = [];
        foreach ($addons as $addon) {
            $options_with_wc_products = [];
            foreach ($addon['options'] as $option) {
                if (
                    isset($option[self::KEY_WC_PRODUCT])
                    && $option[self::KEY_WC_PRODUCT] instanceof \WC_Product) {
                    $options_with_wc_products[] = $option;
                }
            }
            if (!empty($options_with_wc_products)) {
                $addon_with_wc_products[] = $addon;
            }
        }

        return $addon_with_wc_products;
    }

    protected function getProductOtherQuantityFromCart($cart, $wc_product, $key1, array $set_quantities)
    {
        $other_quantity = 0;
        foreach ($cart->get_cart() as $qtyitem) {
            if (
                $qtyitem['product_id'] === $wc_product->get_id()
                && $qtyitem['key'] !== $key1
            ) {
                if (array_key_exists($qtyitem['key'], $set_quantities)) {
                    $other_quantity += $set_quantities[$qtyitem['key']];
                } else {
                    $other_quantity += $qtyitem['quantity'];
                }
            }
        }

        return $other_quantity;
    }

    protected function validateAddonNewCartQuantity($item, $cart, array $set_quantities, $new_quantity): ?string
    {
        if ($item['data'] instanceof \WC_Product) {
            $wc_product = $item['data'];
            $other_quantity = $this->getProductOtherQuantityFromCart($cart, $wc_product, $item['key'], $set_quantities);
            $new_total_qty = $new_quantity + $other_quantity;
            if (!$wc_product->has_enough_stock($new_total_qty)) {
                /* translators: 1: product name 2: quantity in stock */
                $message = sprintf(__('You cannot add that amount of &quot;%1$s&quot; to the cart because there is not enough stock (%2$s remaining).', 'woocommerce'),
                    $wc_product->get_name(),
                    wc_format_stock_quantity_for_display($wc_product->get_stock_quantity(), $wc_product)
                );

                return apply_filters('woocommerce_cart_product_not_enough_stock_message', $message, $wc_product, $new_total_qty);
            }
        }

        return null;
    }
}
