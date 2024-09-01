<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\I18N;
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
    public const FORM_DATA_SK_ADDON_GROUP_ID = 'data-sk-group-id';
    public const FORM_DATA_SK_ADDON_GROUP_ID_JS = 'skGroupId';

    protected $addon_call_cache = [];

    public function registerHooks(): void
    {
        // global
        add_action('wp_head', [$this, 'renderCssStyles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueWcPriceScript']);

        // product page
        add_filter('woocommerce_post_class', [$this, 'addPostSkAddonCssClass'], 10, 2);
        add_action('woocommerce_before_add_to_cart_button', [$this, 'renderAddOnFormOnProductPage']);
        add_action('woocommerce_before_add_to_cart_form', [$this, 'renderPriceCalculationJsScriptOnProductPage']);

        // cart management
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validateCartItemAddQuantity'], 10, 5);
        add_filter('woocommerce_update_cart_validation', [$this, 'validateCartItemQuantityUpdate'], 10, 4);
        add_action('woocommerce_add_to_cart', [$this, 'addSubitemsToCart'], 10, 6);
        add_action('woocommerce_before_calculate_totals', [$this, 'setAddOnPriceOnCartSubitem'], 10, 1);
        add_filter('woocommerce_add_cart_item_data', [$this, 'setAddOnCartItemData'], 10, 3);
        add_filter('woocommerce_cart_item_name', [$this, 'getAddOnOptionNameFromCartItem'], 10, 3);
        add_filter('woocommerce_cart_item_class', [$this, 'appendCssClassToCartItem'], 10, 3);
        add_filter('woocommerce_cart_item_remove_link', [$this, 'disableRemoveLinkForCartSubitems'], 10, 2);
        add_filter('woocommerce_cart_item_quantity', [$this, 'disableQuantityInputForCartSubitems'], 10, 3);
        add_action('woocommerce_cart_item_removed', [$this, 'removeSubitemsForCartItem'], 10, 2);
        add_action('woocommerce_after_cart_item_quantity_update', [$this, 'updateCartSubitemsQuantityForCartItem'], 10, 4);
        add_filter('woocommerce_cart_item_permalink', [$this, 'injectSubitemProductNameForMiniCart'], 10, 3);
        add_action('woocommerce_cart_calculate_fees', [$this, 'addEmballageFee'], 11);

        // order management
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'copyCartItemDataToOrderItem'], 10, 4);
        add_filter('woocommerce_order_item_get_formatted_meta_data', [$this, 'filterOrderItemAddOnMetaForDisplay'], 10, 2);
    }

    public function enqueueWcPriceScript(): void
    {
        wp_enqueue_script('wc-price-js', Core::plugin_url().'/assets/wc_price.js', ['jquery'], '1.0', false);
        $wc_settings = [
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'decimal_separator' => wc_get_price_decimal_separator(),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'currency_format_num_decimals' => wc_get_price_decimals(),
            'price_format' => get_woocommerce_price_format(),
        ];
        wp_localize_script('wc-price-js', 'wc_settings_args', $wc_settings);
    }

    public function addPostSkAddonCssClass($classes, $product): array
    {
        if (is_product() && $this->isProductWithAddOns($product)) {
            $classes[] = 'has-sk-addon';
        }

        return $classes;
    }

    public function filterOrderItemAddOnMetaForDisplay($formatted_meta, $order_item): array
    {
        $valid_meta = [];
        foreach ($formatted_meta as $id => $meta) {
            if (!in_array($meta->key, self::CART_FIELDS)) {
                $valid_meta[$id] = $meta;
            }
        }

        return $valid_meta;
    }

    public function renderAddOnFormOnProductPage(): void
    {
        global $product;

        if (!($product instanceof \WC_Product)) {
            return;
        }
        if (!$this->isProductWithAddOns($product)) {
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

    public function renderPriceCalculationJsScriptOnProductPage()
    {
        global $product;

        if (!$product) {
            return;
        }
        if (!$this->isProductWithAddOns($product)) {
            return;
        }
        $addons = $this->getAddOnsForProduct($product);

        $allowed_addon_groups = [];
        foreach ($addons as $addon) {
            foreach ($addon[self::KEY_WC_PRODUCT] as $wc_product) {
                $id = $wc_product->get_id();
                if (!isset($allowed_addon_groups[$id])) {
                    $allowed_addon_groups[$id] = [];
                }
                $allowed_addon_groups[$id][] = $addon['product_addon_group_id'];
            }
        }
        list($required_price, $price_addon_changes) = $this->calculateRequiredAndOptionalPriceChanges($addons);

        $template_path = Core::plugin_abspath().'templates/';
        $price = $this->getProductSalePrice($product);
        $regularPrice = $this->getProductRegularPrice($product);

        wc_get_template('add-on/js/update-price.php', [
            'product_id' => $product->get_id(),
            'start_price' => $regularPrice,
            'start_sale_price' => $price,
            'required_price' => $required_price,
            'price_addon_changes' => $price_addon_changes,
            'allowed_addon_groups' => $allowed_addon_groups,
        ], '', $template_path);
    }

    public function renderCssStyles()
    {
        $template_path = Core::plugin_abspath().'templates/';
        wc_get_template('add-on/css/add-on-styles.php', [], '', $template_path);
    }

    public function setAddOnCartItemData($cart_item_data, $product_id, $variation_id)
    {
        $product = wc_get_product($product_id);
        if ($this->isProductWithAddOns($product, $variation_id)) {
            $has_add_ons = $this->hasRequiredAddOns($product_id, $variation_id);
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

    public function addSubitemsToCart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        if (
            !empty($cart_item_data[self::CART_FIELD_ID])
            && empty($cart_item_data[self::CART_FIELD_PARENT_ID]) // exclude children
        ) {
            if (!empty($variation_id)) {
                $product_id = $variation_id;
            }
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
                            self::CART_FIELD_ID => uniqid(),
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

    public function setAddOnPriceOnCartSubitem($cart)
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

    public function getAddOnOptionNameFromCartItem($name, $cart_item, $cart_item_key)
    {
        if (!empty($cart_item[self::CART_FIELD_NAME])) {
            return $cart_item[self::CART_FIELD_NAME];
        }

        return $name;
    }

    public function appendCssClassToCartItem($class, $cart_item, $cart_item_key)
    {
        if (isset($cart_item[self::CART_FIELD_PARENT_ID])) {
            $class .= ' '.self::CSS_CLASS_ADDON_SUBPRODUCT;
        } elseif (isset($cart_item[self::CART_FIELD_ID])) {
            $class .= ' '.self::CSS_CLASS_ADDON_PRODUCT;
        }

        return $class;
    }

    public function disableQuantityInputForCartSubitems($quantity_input, $cart_item_key, $cart_item)
    {
        if (isset($cart_item[self::CART_FIELD_PARENT_ID])) {
            return '';
        }

        return $quantity_input;
    }

    public function disableRemoveLinkForCartSubitems($link, $cart_item_key)
    {
        $cart = WC()->cart->get_cart();
        $cart_item = $cart[$cart_item_key];
        if (isset($cart_item[self::CART_FIELD_PARENT_ID])) {
            return '';
        }

        return $link;
    }

    public function copyCartItemDataToOrderItem(\WC_Order_Item_Product $item, $cart_item_key, $cart_item_data, \WC_Order $order)
    {
        if (!empty($cart_item_data[self::CART_FIELD_ID])) {
            foreach (self::CART_FIELDS as $field_name) {
                if (isset($cart_item_data[$field_name])) {
                    $item->add_meta_data($field_name, $cart_item_data[$field_name]);
                }
            }
        }
    }

    public function injectSubitemProductNameForMiniCart($product_permalink, $cart_item, $cart_item_key)
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

        $shop_product_ids = [(int) $shop_product_id];
        if ($product instanceof \WC_Product_Variable) {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                $variation_id = $variation['variation_id'];
                $shop_product_id = get_post_meta($variation_id, 'storekeeper_id', true);
                if (!empty($shop_product_id)) {
                    $shop_product_ids[] = (int) $shop_product_id;
                }
            }
        }

        $shop_product_ids = array_unique($shop_product_ids);
        sort($shop_product_ids);
        $key = implode('|', $shop_product_ids);

        if (!array_key_exists($key, $this->addon_call_cache)) {
            $addons = $this->getAddOnsFromApi($shop_product_ids);
            $addons = $this->addWcProductsToAddons($addons);
            $addons = $this->filterAddonsWithWcProducts($addons);

            $this->addon_call_cache[$key] = $addons;
        }

        return $this->addon_call_cache[$key];
    }

    protected function getAddOnsFromApi(array $shop_product_ids): array
    {
        try {
            $api = StoreKeeperApi::getApiByAuthName();
            $ShopModule = $api->getModule('ShopModule');
            $addon_groups = $ShopModule->getShopProductAddonIdsForHook($shop_product_ids);
            if (empty($addon_groups)) {
                return [];
            }

            $product_addon_group_ids = [];
            foreach ($addon_groups as $addon) {
                $product_addon_group_ids = array_merge($product_addon_group_ids, $addon['product_addon_group_ids'] ?? []);
            }

            if (empty($product_addon_group_ids)) {
                return [];
            }
            $product_addon_group_ids = array_unique($product_addon_group_ids);

            $formatted_addons = [];
            foreach ($product_addon_group_ids as $product_addon_group_id) {
                $group = $ShopModule->getShopProductAddonGroup($product_addon_group_id);

                $formatted_addon = [
                    'product_addon_group_id' => $product_addon_group_id,
                    'title' => $group['product_addon_group']['title'],
                    'type' => $group['product_addon_group']['type'],
                    'options' => [],
                    'order' => $group['product_addon_group']['order'],
                    'shop_product_ids' => [],
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

                $formatted_addons[$product_addon_group_id] = $formatted_addon;
            }

            foreach ($addon_groups as $addon_group) {
                $shop_product_id = $addon_group['id'];
                foreach ($addon_group['product_addon_group_ids'] as $product_addon_group_id) {
                    $formatted_addons[$product_addon_group_id]['shop_product_ids'][] = $shop_product_id;
                }
            }

            usort($formatted_addons, function ($a, $b) {
                $res = $a['order'] <=> $b['order'];
                if (0 === $res) {
                    $res = $a['product_addon_group_id'] <=> $b['product_addon_group_id'];
                }

                return $res;
            });

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
                    '' => sprintf(
                        __('No %s selected', I18N::DOMAIN),
                        $addon['title']
                    ),
                ];
                unset($option);
                foreach ($addon['options'] as &$option) {
                    $field_options[$option['id']] = $option[self::OPTION_TITLE];
                }
                $addon[self::KEY_FORM_OPTIONS] = [
                    'type' => 'select',
                    'class' => ['sk-addon-input'],
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
                        'class' => ['sk-addon-input'],
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

    protected function calculateRequiredAndOptionalPriceChanges(array $addons): array
    {
        $required_price = 0;
        $price_addon_changes = [];
        foreach ($addons as $addon) {
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

    public function removeSubitemsForCartItem($cart_item_key, $cart)
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

    public function validateCartItemQuantityUpdate($passed, $cart_item_key, $cart_item, $quantity)
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

    public function validateCartItemAddQuantity($passed, $product_id, $quantity, $variation_id = '', $variations = '')
    {
        $cart_item = $this->setAddOnCartItemData([], $product_id, $variation_id);
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

    public function updateCartSubitemsQuantityForCartItem($cart_item_key, $new_quantity, $old_quantity = null, $cart = null)
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
        if ($product instanceof \WC_Product_Variable) {
            $price = $product->get_variation_sale_price();
            if (empty($price)) {
                $price = $product->get_variation_regular_price();
            }
        } else {
            $price = $product->get_sale_price('edit');
            if (empty($price)) {
                $price = $product->get_regular_price('edit');
            }
        }
        $price = floatval($price);

        return $price;
    }

    protected function getProductRegularPrice(\WC_Product $product): float
    {
        if ($product instanceof \WC_Product_Variable) {
            $price = $product->get_variation_regular_price();
        } else {
            $price = $product->get_regular_price('edit');
        }
        $price = floatval($price);

        return $price;
    }

    protected function isProductWithAddOns(\WC_Product $product, $variation_id = null): bool
    {
        $has_addons = '1' === $product->get_meta(ProductImport::META_HAS_ADDONS, true);
        if (!$has_addons) {
            $check_ids = [];
            if ($product instanceof \WC_Product_Variable) {
                // if variable also check is any children have addon
                $check_ids = $product->get_visible_children();
            }
            if (!empty($variation_id)) {
                $check_ids[] = $variation_id;
            }
            if (!empty($check_ids)) {
                $exists = get_posts([
                    'post_type' => ['product_variation'],
                    'numberposts' => 1,
                    'meta_key' => ProductImport::META_HAS_ADDONS,
                    'meta_value' => '1',
                    'fields' => 'ids',
                    'include' => $check_ids,
                ]);
                $has_addons = !empty($exists);
            }
        }

        return $has_addons;
    }

    protected function hasRequiredAddOns(int $product_id, $variation_id = null): bool
    {
        $product = empty($variation_id) ?
            wc_get_product($product_id) : wc_get_product($variation_id);

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
            $shop_product_ids = array_merge($addon['shop_product_ids'], $shop_product_ids);
            foreach ($addon['options'] as $option) {
                $shop_product_ids[] = $option['shop_product_id'];
            }
        }
        $shop_product_ids = array_values(array_unique($shop_product_ids));

        foreach ($shop_product_ids as $shop_product_id) {
            $products = get_posts([
                'post_type' => ['product', 'product_variation'],
                'numberposts' => 1,
                'meta_key' => 'storekeeper_id',
                'meta_value' => $shop_product_id,
                'fields' => 'ids',
            ]);
            if (!empty($products)) {
                $wc_product_per_id[$shop_product_id] = wc_get_product($products[0]);
            }
        }

        unset($addon, $option);
        foreach ($addons as &$addon) {
            foreach ($addon['options'] as &$option) {
                $shop_product_id = $option['shop_product_id'];
                if (array_key_exists($shop_product_id, $wc_product_per_id)) {
                    $option[self::KEY_WC_PRODUCT] = $wc_product_per_id[$shop_product_id];
                }
            }

            $wc_products = [];
            foreach ($addon['shop_product_ids'] as $shop_product_id) {
                if (array_key_exists($shop_product_id, $wc_product_per_id)) {
                    $wc_products[] = $wc_product_per_id[$shop_product_id];
                }
            }
            $addon[self::KEY_WC_PRODUCT] = $wc_products;
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
                $addon['options'] = $options_with_wc_products;
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

    public function addEmballageFee()
    {
        /* @var \WooCommerce $woocommerce */
        global $woocommerce;

        $items = $woocommerce->cart->get_cart();

        $lastEmballageTaxRateId = null;
        $totalEmballagePriceInCents = 0;
        foreach ($items as $values) {
            $product = $values['data'];
            if (!($product instanceof \WC_Product)) {
                continue;
            }

            $addFee = !$this->isProductWithAddOns($product, $values['variation_id'])
                && !$this->hasRequiredAddOns($values['product_id']);

            if ($addFee) {
                // only use legacy embalage if product is not synchronized as with addons
                // otherwise it will be added double;

                if ($product) {
                    $quantity = $values['quantity'];
                    if ($product->meta_exists(ProductImport::PRODUCT_EMBALLAGE_PRICE_META_KEY)) {
                        $emballagePrice = $product->get_meta(ProductImport::PRODUCT_EMBALLAGE_PRICE_META_KEY);
                        $totalEmballagePriceInCents += round($emballagePrice * 100) * $quantity;
                    }

                    if ($product->meta_exists(ProductImport::PRODUCT_EMBALLAGE_TAX_ID_META_KEY)) {
                        $lastEmballageTaxRateId = $product->get_meta(ProductImport::PRODUCT_EMBALLAGE_TAX_ID_META_KEY);
                    }
                }
            }
        }

        if ($totalEmballagePriceInCents > 0) {
            $totalEmballagePrice = round($totalEmballagePriceInCents / 100, 2);
            $emballagePrice = [
                'name' => __('Emballage fee', I18N::DOMAIN),
                'amount' => $totalEmballagePrice,
                OrderExport::IS_EMBALLAGE_FEE_KEY => true,
            ];
            if ($lastEmballageTaxRateId) {
                $emballagePrice[OrderExport::TAX_RATE_ID_FEE_KEY] = $lastEmballageTaxRateId;
            }
            $woocommerce->cart->fees_api()->add_fee($emballagePrice);
        }
    }
}
