<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\DashboardPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\LogsPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\SettingsPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\StatusPage;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\ToolsPage;
use StoreKeeper\WooCommerce\B2C\Helpers\RoleHelper;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Objects\PluginStatus;

class MenuStructure
{
    public const CAPABILITY_ADMIN = 'storekeeper_admin_capability';

    public function registerCapability()
    {
        $role = get_role('administrator');
        $role->add_cap(self::CAPABILITY_ADMIN, true);
    }

    public static function registerStyle()
    {
        wp_enqueue_style('storekeeper_menu_style', plugin_dir_url(__FILE__).'/static/menu.css');

        if (PluginStatus::isProductXEnabled()) {
            if (RoleHelper::isContentManager()) {
                wp_enqueue_script('storekeeper_productx_adjust', plugin_dir_url(__FILE__).'/static/productx-adjust.js');
            }
            $_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
            $is_active = wopb_function()->is_lc_active();
            $license_key = get_option('edd_wopb_license_key');
            if ('wopb-builder' == $_page || 'wopb_builder' == get_post_type(get_the_ID())) {
                wp_enqueue_script('wopb-conditions-script', WOPB_URL.'addons/builder/assets/js/conditions.min.js', ['wp-api-fetch', 'wp-components', 'wp-i18n', 'wp-blocks'], WOPB_VER, true);
                wp_localize_script('wopb-conditions-script', 'wopb_condition', [
                    'url' => WOPB_URL,
                    'active' => $is_active,
                    'premium_link' => wopb_function()->get_premium_link(),
                    'license' => $is_active ? get_option('edd_wopb_license_key') : '',
                    'builder_url' => admin_url('admin.php?page=wopb-builder'),
                    'builder_type' => get_the_ID() ? get_post_meta(get_the_ID(), '_wopb_builder_type', true) : '',
                    'home_page_display' => get_option('show_on_front'),
                ]);
                wp_enqueue_script('wopb-dashboard-script', WOPB_URL.'assets/js/wopb_dashboard_min.js', ['wp-i18n', 'wp-api-fetch', 'wp-api-request', 'wp-components', 'wp-blocks'], WOPB_VER, true);
                wp_localize_script('wopb-dashboard-script', 'wopb_dashboard_pannel', [
                    'url' => WOPB_URL,
                    'active' => $is_active,
                    'license' => $license_key,
                    'settings' => wopb_function()->get_setting(),
                    'addons' => wopb_function()->all_addons(),
                    'addons_settings' => apply_filters('wopb_settings', []),
                    'premium_link' => wopb_function()->get_premium_link(),
                    'builder_url' => admin_url('admin.php?page=wopb-builder'),
                    'affiliate_id' => apply_filters('wopb_affiliate_id', false),
                    'version' => WOPB_VER,
                    'setup_wizard_link' => admin_url('admin.php?page=wopb-initial-setup-wizard'),
                    'helloBar' => get_transient('wopb_helloBar'),
                    'status' => get_option('edd_wopb_license_status'),
                    'expire' => get_option('edd_wopb_license_expire'),
                    'whats_new_link' => admin_url('admin.php?page=wopb-whats-new'),
                ]);
                wp_set_script_translations('wopb-dashboard-script', 'product-blocks', WOPB_PATH.'languages/');
            }
        }
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
