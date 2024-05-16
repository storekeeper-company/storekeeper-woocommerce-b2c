<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Helpers;

use StoreKeeper\WooCommerce\B2C\Helpers\RoleHelper;
use WOPB\RequestAPI;

class ProductXEditor extends RequestAPI
{
    public static function loadProduxtXScripts(): void
    {
        if (version_compare(WOPB_VER, '3.1.15', '>=')) {
            self::loadProduxtXScripts_3_1_15();
        } else {
            self::loadProduxtXScripts_3_1_5();
        }
    }

    protected static function loadProduxtXScripts_3_1_15(): void
    {
        // copy from ./wp-content/plugins/product-blocks/classes/Initialization.php
        // function register_scripts_option_panel_callback
        // with changes to builder_url = page=wopb-builder

        $_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
        $is_active = wopb_function()->is_lc_active();
        $license_key = get_option('edd_wopb_license_key');
        if ('wopb-builder' == $_page || 'wopb_builder' == get_post_type(get_the_ID())) {
            $post_id = get_the_ID();

            wp_enqueue_script('wopb-conditions-script', WOPB_URL.'addons/builder/assets/js/conditions.min.js', ['wp-api-fetch', 'wp-components', 'wp-i18n', 'wp-blocks'], WOPB_VER, true);
            wp_localize_script('wopb-conditions-script', 'wopb_condition', [
                'url' => WOPB_URL,
                'active' => $is_active,
                'license' => $is_active ? get_option('edd_wopb_license_key') : '',
                'builder_url' => admin_url('admin.php?page=wopb-builder#builder'),
                'builder_type' => $post_id ? get_post_meta($post_id, '_wopb_builder_type', true) : '',
            ]);
            global $wopb_default_settings;
            $query_args = [
                'posts_per_page' => 3,
                'post_type' => 'product',
                'post_status' => 'publish',
            ];
            wp_enqueue_script('wopb-dashboard-script', WOPB_URL.'assets/js/wopb_dashboard_min.js', ['wp-i18n', 'wp-api-fetch', 'wp-api-request', 'wp-components', 'wp-blocks'], WOPB_VER, true);
            wp_localize_script('wopb-dashboard-script', 'wopb_dashboard_pannel', [
                'url' => WOPB_URL,
                'active' => $is_active,
                'license' => $license_key,
                'settings' => wopb_function()->get_setting(),
                'addons' => apply_filters('wopb_addons_config', []),
                'addons_settings' => apply_filters('wopb_settings', []),
                'default_settings' => $wopb_default_settings,
                'builder_url' => admin_url('admin.php?page=wopb-builder'),
                'affiliate_id' => apply_filters('wopb_affiliate_id', false),
                'version' => WOPB_VER,
                'setup_wizard_link' => admin_url('admin.php?page=wopb-initial-setup-wizard'),
                'helloBar' => get_transient('wopb_helloBar'),
                'status' => get_option('edd_wopb_license_status'),
                'expire' => get_option('edd_wopb_license_expire'),
                'products' => wopb_function()->is_wc_ready() ? wopb_function()->product_format(['products' => new \WP_Query($query_args), 'size' => 'medium']) : [],
            ]);
            wp_set_script_translations('wopb-dashboard-script', 'product-blocks', WOPB_PATH.'languages/');
        }
    }

    protected static function loadProduxtXScripts_3_1_5(): void
    {
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

    public static function checkPermissions(): bool
    {
        // add our capability to all routes
        return current_user_can('manage_options') || current_user_can(RoleHelper::CAP_CONTENT_BUILDER);
    }

    public function registerRoutes()
    {
        // copy from parent::get_template_data() with changed 'permission_callback'
        register_rest_route(
            'wopb/v2',
            '/get_single_premade/',
            [
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'get_single_premade_callback'],
                    'permission_callback' => function () {
                        return self::checkPermissions();
                    },
                    'args' => [],
                ],
            ]
        );
        register_rest_route(
            'wopb/v2',
            '/condition/',
            [
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'condition_settings_action'],
                    'permission_callback' => function () {
                        return self::checkPermissions();
                    },
                    'args' => [],
                ],
            ]
        );
        register_rest_route(
            'wopb/v2',
            '/condition_save/',
            [
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'condition_save_action'],
                    'permission_callback' => function () {
                        return self::checkPermissions();
                    },
                    'args' => [],
                ],
            ]
        );
        register_rest_route(
            'wopb/v2',
            '/data_builder/',
            [
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'data_builder_action'],
                    'permission_callback' => function () {
                        return self::checkPermissions();
                    },
                    'args' => [],
                ],
            ]
        );
        register_rest_route(
            'wopb/v2',
            '/template_action/',
            [
                [
                    'methods' => 'POST',
                    'callback' => [$this, 'template_page_action'],
                    'permission_callback' => function () {
                        return self::checkPermissions();
                    },
                    'args' => [],
                ],
            ]
        );
    }
}
