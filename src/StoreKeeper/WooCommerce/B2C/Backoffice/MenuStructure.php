<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice;

use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\ProductXEditor;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\DashboardPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\LogsPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\SettingsPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\StatusPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\ToolsPage;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Helpers\RoleHelper;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Objects\PluginStatus;

class MenuStructure implements WithHooksInterface
{
    public function registerHooks(): void
    {
        add_action('init', [$this, 'registerCapability']);
        add_action('admin_menu', [$this, 'registerMenu'], 99);
        add_action('admin_enqueue_scripts', [$this, 'registerStyle']);
    }

    public function registerCapability()
    {
        $role = get_role('administrator');
        $role->add_cap(RoleHelper::CAP_CONTENT_BUILDER, true);
    }

    public static function registerStyle()
    {
        wp_enqueue_style('storekeeper_menu_style', plugin_dir_url(__FILE__).'/static/menu.css');

        if (PluginStatus::isProductXEnabled()) {
            try {
                if (RoleHelper::isContentManager()) {
                    wp_enqueue_script('storekeeper_productx_adjust', plugin_dir_url(__FILE__).'/static/productx-adjust.js');
                }
                ProductXEditor::loadProduxtXScripts();
            } catch (\Throwable $e) {
                LoggerFactory::create('load_errors')->error(
                    'Woocommerce Builder cannot be loaded:  '.$e->getMessage(),
                    [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                    ]
                );
                wp_die(
                    __('Woocommerce Builder cannot be loaded.', I18N::DOMAIN).
                    sprintf(__(' It was tested with version builder version: %s. ', I18N::DOMAIN), PluginStatus::PRODUCT_X_COMPATIBLE_VERSION),
                );
            }
        }
    }

    public function registerMenu()
    {
        list($pages, $dashboardPage) = self::getPages();

        add_menu_page(
            __('StoreKeeper', I18N::DOMAIN),
            __('StoreKeeper', I18N::DOMAIN),
            RoleHelper::CAP_CONTENT_BUILDER,
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
                RoleHelper::CAP_CONTENT_BUILDER,
                $subPage->getSlug(),
                [$subPage, 'initialize']
            );
        }

        if (RoleHelper::isContentManager() && PluginStatus::isProductXEnabled()) {
            remove_menu_page('wopb-settings');

            $wopb_menu_icon = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA1MCA1MCI+PHBhdGggZD0iTTQxLjggMTIuMDdoLTUuMjZ2LS41NEMzNi41NCA1LjE4IDMxLjM3IDAgMjUgMGMtNi4zNiAwLTExLjU0IDUuMTgtMTEuNTQgMTEuNTR2LjU0SDguMjFjLTQuMjkgMC03LjU2IDMuODItNi44OSA4LjA2bDMuNzkgMjMuOTlDNS42NCA0Ny41IDguNTYgNTAgMTIgNTBoMjYuMDFjMy40MyAwIDYuMzYtMi41IDYuODgtNS44OGwzLjc5LTIzLjk5Yy42Ny00LjI0LTIuNjEtOC4wNi02Ljg4LTguMDZ6TTE2IDExLjU0YzAtNC45NiA0LjA0LTkgOS05czkgNC4wNCA5IDl2LjU0SDE2di0uNTR6bTExLjk5IDEyLjQ4YzAgLjE0LS4wNC4zLS4xMi40NGwtMS41MiAyLjctMS4xIDEuOTJjLS4xMS4xOS0uMzguMTktLjQ5IDBsLTIuNjMtNC42MmMtLjMzLS42LjEtMS4zMy43Ny0xLjMzaDQuMTljLjUzIDAgLjkuNDMuOS44OXptLTcuNTYgMTIuNzFjLS4zMy42LTEuMi42LTEuNTUgMGwtLjY1LTEuMTUtNS4yNS05LjIzYy0uNjUtMS4xNC0uMi0yLjUuODEtMy4xMi4yNS0uMTUuNTUtLjI3Ljg2LS4zMS4xMi0uMDIuMjMtLjAyLjM1LS4wMmgyLjE0YzEuMSAwIDIuMS41OCAyLjYzIDEuNTJsLjIzLjM5IDIuMzEgNC4wOC44OCAxLjU0Yy4yNS40NS4yNSAxLjAxIDAgMS40NmwtMiAzLjUxLS43NiAxLjMzem02LjY3IDIuNDVoLTQuMmMtLjUxIDAtLjg5LS40My0uODktLjg5IDAtLjE1LjA0LS4zLjEyLS40NGwxLjU0LTIuNjkgMS4xLTEuOTNjLjExLS4xOS4zOC0uMTkuNDkgMGwyLjYyIDQuNjJhLjg5My44OTMgMCAwMS0uNzggMS4zM3ptOS45NC0xMi44M2wtNS45MiAxMC4zOGMtLjMzLjYtMS4yLjYtMS41NSAwbC0uNzUtMS4zMi0xLjk5LTMuNTFjLS4yNi0uNDUtLjI2LTEuMDEgMC0xLjQ2bC44Ny0xLjU0IDIuMzItNC4wOC4yMS0uMzlhMy4wNCAzLjA0IDAgMDEyLjYzLTEuNTJoMi4xNWMuNDUgMCAuODYuMTIgMS4yLjMzYTIuMjkgMi4yOSAwIDAxLjgzIDMuMTF6IiBmaWxsPSIjYTdhYWFkIi8+PC9zdmc+';
            add_menu_page(
                'wopb-builder',
                esc_html__('Webshop Builder', I18N::DOMAIN),
                RoleHelper::CAP_CONTENT_BUILDER,
                'wopb-builder',
                function () {
                    echo '<div id="wopb-dashboard"></div>';
                },
                $wopb_menu_icon,
                10.5
            );
        }
    }

    public static function getPages(): array
    {
        $dashboardPage = new DashboardPage(
            __('Dashboard', I18N::DOMAIN),
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
