<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\DashboardPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\LogsPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\SettingsPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\StatusPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\ToolsPage;
use StoreKeeper\WooCommerce\B2C\I18N;

class MenuStructure
{
    const CAPABILITY_ADMIN = 'storekeeper_admin_capability';

    public function registerCapability()
    {
        $role = get_role('administrator');
        $role->add_cap(self::CAPABILITY_ADMIN, true);
    }

    public static function registerStyle()
    {
        wp_enqueue_style('storekeeper_menu_style', plugin_dir_url(__FILE__).'/static/menu.css');
    }

    public function registerMenu()
    {
        list($pages, $dashboardPage) = self::getPages();

        add_menu_page(
            __('StoreKeeper', I18N::DOMAIN),
            __('StoreKeeper', I18N::DOMAIN),
            self::CAPABILITY_ADMIN,
            $dashboardPage->getSlug(),
            null,
            plugin_dir_url(__FILE__).'/static/menu-icon.png',
            '59.1' // Below the divider below WooCommerce
        );

        /** @var AbstractPage $subPage */
        foreach ($pages as $subPage) {
            add_submenu_page(
                $dashboardPage->getSlug(),
                $subPage->title,
                $subPage->title,
                self::CAPABILITY_ADMIN,
                $subPage->getSlug(),
                [$subPage, 'initialize']
            );
        }
    }

    public static function getPages(): array
    {
        $dashboardPage = new DashboardPage(
            __('Dashboard', I18n::DOMAIN),
            'dashboard'
        );
        $pages = [
            $dashboardPage,
            new LogsPage(
                __('Logs', I18N::DOMAIN),
                'logs'
            ),
            new ToolsPage(
                __('Tools', I18N::DOMAIN),
                'tools'
            ),
            new SettingsPage(
                __('Settings', I18N::DOMAIN),
                'settings'
            ),
            new StatusPage(
                __('Status', I18N::DOMAIN),
                'status'
            ),
        ];

        return [$pages, $dashboardPage];
    }
}
