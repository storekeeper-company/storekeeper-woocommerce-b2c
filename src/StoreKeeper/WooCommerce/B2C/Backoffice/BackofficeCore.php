<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice;

use StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes\OrderSyncMetaBox;
use StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes\ProductSyncMetaBox;
use StoreKeeper\WooCommerce\B2C\Backoffice\Notices\AdminNotices;
use StoreKeeper\WooCommerce\B2C\Tools\ActionFilterLoader;

class BackofficeCore
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

        $this->settings();
        $this->adminNotices();
        $this->orderSyncMetaBoxes();
        $this->productSyncMetaBoxes();
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
        $this->loader->run();
    }

    private function productSyncMetaBoxes()
    {
        $metaBox = new ProductSyncMetaBox();
        $this->loader->add_action('add_meta_boxes', $metaBox, 'register');
    }

    private function orderSyncMetaBoxes()
    {
        $metaBox = new OrderSyncMetaBox();
        $this->loader->add_action('post_action_'.OrderSyncMetaBox::ACTION_NAME, $metaBox, 'action');
        $this->loader->add_action('add_meta_boxes', $metaBox, 'register');
    }

    private function loadStorekeeperMenu()
    {
        $menuStructure = new MenuStructure();
        $this->loader->add_action('init', $menuStructure, 'registerCapability');
        $this->loader->add_action('admin_menu', $menuStructure, 'registerMenu');
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
}
