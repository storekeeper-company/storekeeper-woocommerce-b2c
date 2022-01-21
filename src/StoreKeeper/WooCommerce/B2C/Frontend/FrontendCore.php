<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend;

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\AddressFormHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\OrderHookHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo;
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

        $seo = new Seo();
        $this->loader->add_filter('woocommerce_structured_data_product', $seo, 'prepareSeo', 10, 2);

        $orderHookHandler = new OrderHookHandler();
        $this->loader->add_action('woocommerce_order_details_after_order_table', $orderHookHandler, 'addOrderStatusLink');
        $this->loader->add_filter(OrderHookHandler::STOREKEEPER_ORDER_TRACK_HOOK, $orderHookHandler, 'createOrderTrackingMessage', 10, 2);

        $this->registerShortCodes();
        $this->registerHandlers();
        $this->loadWooCommerceTemplate();
        $this->registerStyle();
        $this->registerRedirects();

        if ('yes' === StoreKeeperOptions::get(StoreKeeperOptions::VALIDATE_CUSTOMER_ADDRESS, 'yes')) {
            $this->registerAddressFormHandler();
        }
    }

    public function run()
    {
        $this->loader->run();
    }

    private function registerAddressFormHandler(): void
    {
        $addressFormHandler = new AddressFormHandler();

        // Form altering and validation
        $this->loader->add_filter('woocommerce_default_address_fields', $addressFormHandler, 'alterAddressForm', 11);
        $this->loader->add_filter('woocommerce_get_country_locale', $addressFormHandler, 'customLocale', 11);
        $this->loader->add_filter('woocommerce_country_locale_field_selectors', $addressFormHandler, 'customSelectors', 11);
        $this->loader->add_action('woocommerce_account_edit-address_endpoint', $addressFormHandler, 'enqueueScriptsAndStyles');
        $this->loader->add_action('woocommerce_after_save_address_validation', $addressFormHandler, 'validateCustomFields', 11, 2);
        $this->loader->add_action('woocommerce_checkout_process', $addressFormHandler, 'validateCustomFieldsForCheckout', 11, 2);
        $this->loader->add_action('woocommerce_checkout_create_order', $addressFormHandler, 'saveCustomFields');
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

    private function loadWooCommerceTemplate()
    {
        $this->loader->add_action('after_setup_theme', $this, 'includeTemplateFunctions', 10);
        // Register actions that use global functions.
        add_action('woocommerce_after_shop_loop', 'woocommerce_taxonomy_archive_summary', 100);
        add_action('woocommerce_no_products_found', 'woocommerce_taxonomy_archive_summary', 100);

        // Add the markdown parsers
        add_filter('the_content', 'woocommerce_markdown_description');
        add_filter('woocommerce_short_description', 'woocommerce_markdown_short_description');
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

    public static function includeTemplateFunctions()
    {
        include_once __DIR__.'/Templates/wc-template-functions.php';
    }
}
