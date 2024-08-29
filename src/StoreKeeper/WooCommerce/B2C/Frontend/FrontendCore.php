<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend;

use StoreKeeper\WooCommerce\B2C\Frontend\Filters\OrderTrackingMessage;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\AddressFormHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\CategorySummaryHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\CustomerEmailHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\MarkdownHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\OrderHookHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\ProductAddOnHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\StoreKeeperSeoHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\SubscribeHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\ShortCodes\FormShortCode;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\ActionFilterLoader;
use StoreKeeper\WooCommerce\B2C\Tools\RedirectHandler;

class FrontendCore
{
    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    0.0.1
     *
     * @var ActionFilterLoader maintains and registers all hooks for the plugin
     */
    protected $loader;

    /**
     * Core constructor.
     */
    public function __construct()
    {
        $this->loader = new ActionFilterLoader();

        $orderHookHandler = new OrderHookHandler();
        $this->loader->add_action('woocommerce_order_details_after_order_table', $orderHookHandler, 'addOrderStatusLink');
        $this->loader->add_filter(OrderTrackingMessage::getTag(), $orderHookHandler, 'createOrderTrackingMessage', 10, 2);
        $this->loader->add_action('woocommerce_checkout_create_order_fee_item', $orderHookHandler, 'addEmballageTaxRateId', 11, 4);

        $this->registerShortCodes();
        $this->registerHandlers();
        $this->registerStyle();
        $this->registerRedirects();
        if ('yes' === StoreKeeperOptions::get(StoreKeeperOptions::VALIDATE_CUSTOMER_ADDRESS, 'yes')) {
            $this->registerAddressFormHandler();
        }
    }

    public function run()
    {
        $seo = new StoreKeeperSeoHandler();
        $seo->registerHooks();

        $cartHandler = new CustomerEmailHandler();
        $cartHandler->registerHooks();

        $categorySummray = new CategorySummaryHandler();
        $categorySummray->registerHooks();

        $markdown = new MarkdownHandler();
        $markdown->registerHooks();

        $this->loader->run();

        (new ProductAddOnHandler())->registerHooks();
    }

    private function registerAddressFormHandler(): void
    {
        $addressFormHandler = new AddressFormHandler();

        // Form altering and validation
        $this->loader->add_filter('woocommerce_default_address_fields', $addressFormHandler, 'alterAddressForm', 11);
        $this->loader->add_filter('woocommerce_get_country_locale', $addressFormHandler, 'customLocale', 11);
        $this->loader->add_filter('woocommerce_country_locale_field_selectors', $addressFormHandler, 'customSelectors', 11);
        $this->loader->add_filter('woocommerce_billing_fields', $addressFormHandler, 'setHouseNumberValueFromSession', 11);
        $this->loader->add_filter('woocommerce_shipping_fields', $addressFormHandler, 'setHouseNumberValueFromSession', 11);

        $this->loader->add_action('woocommerce_before_edit_account_address_form', $addressFormHandler, 'enqueueScriptsAndStyles');
        $this->loader->add_action('woocommerce_checkout_create_order', $addressFormHandler, 'saveCustomFields');
        $this->loader->add_action('woocommerce_checkout_process', $addressFormHandler, 'saveCustomFieldsToSession');
        $this->loader->add_action('woocommerce_before_checkout_form', $addressFormHandler, 'addCheckoutScripts', 11);
    }

    private function registerRedirects()
    {
        $redirectHandler = new RedirectHandler();

        $this->loader->add_action('init', $redirectHandler, 'redirect');
    }

    private function registerShortCodes()
    {
        $this->loader->add_filter('init', new FormShortCode(), 'load');
    }

    private function registerHandlers()
    {
        $subscribeHandler = new SubscribeHandler();
        $this->loader->add_filter('init', $subscribeHandler, 'register');
    }

    private function registerStyle()
    {
        add_action(
            'wp_enqueue_style',
            function () {
                $style = 'assets/backoffice-sync.css';
                \wp_enqueue_style(
                    STOREKEEPER_WOOCOMMERCE_B2C_NAME.basename($style),
                    plugins_url($style, __FILE__),
                    [],
                    STOREKEEPER_WOOCOMMERCE_B2C_VERSION,
                    'all'
                );
            }
        );
    }
}
