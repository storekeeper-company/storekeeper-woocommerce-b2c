<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice;

use Blocksy\Plugin;
use StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes\OrderSyncMetaBox;
use StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes\ProductSyncMetaBox;
use StoreKeeper\WooCommerce\B2C\Backoffice\Notices\AdminNotices;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\StoreKeeperSeoPages;
use StoreKeeper\WooCommerce\B2C\Commands\ImportStartingSite\BlocksyDemoInstall;
use StoreKeeper\WooCommerce\B2C\Models\ShippingZoneModel;
use StoreKeeper\WooCommerce\B2C\Objects\PluginStatus;
use StoreKeeper\WooCommerce\B2C\Options\AbstractOptions;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\ActionFilterLoader;

class BackofficeCore
{
    public const DOCS_WPCLI_LINK = 'https://wp-cli.org/#installing';
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

        $this->settings();
        $this->adminNotices();
        $this->metaBoxes();

        if (self::isShippingMethodUsed()) {
            $this->loader->add_action('admin_enqueue_scripts', $this, 'addShippingMethodScripts');
        }
    }

    private function settings()
    {
        $this->loadStorekeeperMenu();
        $this->loadStorekeeperAdminStyles();
    }

    private function adminNotices()
    {
        $adminNotices = new AdminNotices();
        $this->loader->add_action('admin_notices', $adminNotices, 'emitNotices');
    }

    public function run()
    {
        $Seo = new StoreKeeperSeoPages();
        $Seo->registerHooks();

        $this->loader->run();

        if( PluginStatus::isBlocksyEnabled() ){
            add_action('init', [$this, 'blocksyInit'], 0); // blocksy is 0
        }
    }

    public function blocksyInit() {
        $blocksy = Plugin::instance();
        $blocksy->demo = new BlocksyDemoInstall($blocksy->demo,[
            __DIR__.'/../../../../../tmp/SKTest.json' // todo
        ]);
    }

    private function metaBoxes(): void
    {
        $orderSyncMetaBox = new OrderSyncMetaBox();
        $productSyncMetaBox = new ProductSyncMetaBox();

        // Order sync meta box
        $this->loader->add_action('add_meta_boxes', $orderSyncMetaBox, 'register');
        $this->loader->add_action('post_action_'.OrderSyncMetaBox::ACTION_NAME, $orderSyncMetaBox, 'doSync');

        // Product sync meta box
        $this->loader->add_action('add_meta_boxes', $productSyncMetaBox, 'register');
        $this->loader->add_action('post_action_'.ProductSyncMetaBox::ACTION_NAME, $productSyncMetaBox, 'doSync');
    }

    private function loadStorekeeperMenu()
    {
        $menuStructure = new MenuStructure();
        $this->loader->add_action('init', $menuStructure, 'registerCapability');
        $this->loader->add_action('admin_menu', $menuStructure, 'registerMenu', 99);
        $this->loader->add_action('admin_enqueue_scripts', $menuStructure, 'registerStyle');
    }

    private function loadStorekeeperAdminStyles()
    {
        $this->loader->add_action('init', $this, 'registerAdminStyles');
    }

    public function registerAdminStyles()
    {
        $this->addStyle(
            plugin_dir_url(__FILE__).'/static/storekeeperOverlay.css'
        );
    }

    private function addStyle(string $stylePath)
    {
        wp_enqueue_style(
            "storekeeper-style-$stylePath",
            $stylePath
        );
    }

    public function addShippingMethodScripts(): void
    {
        $woocommerceZoneIds = ShippingZoneModel::getWoocommerceZoneIds();
        if (!empty($woocommerceZoneIds)) {
            $shippingMethodHandle = AbstractOptions::PREFIX.'-shipping-method-overrides';
            wp_enqueue_script($shippingMethodHandle, plugin_dir_url(__FILE__).'/static/shipping-methods.override.js');
            wp_localize_script($shippingMethodHandle, 'shippingZones',
                [
                    'ids' => $woocommerceZoneIds,
                ]
            );
        }
    }

    public static function isShippingMethodUsed(): bool
    {
        return 'yes' === StoreKeeperOptions::get(StoreKeeperOptions::SHIPPING_METHOD_ACTIVATED, 'no') && StoreKeeperOptions::isShippingMethodAllowedForCurrentSyncMode();
    }
}
