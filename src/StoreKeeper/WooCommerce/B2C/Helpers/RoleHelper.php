<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Objects\PluginStatus;

class RoleHelper
{
    public const ROLE_WEBSHOP_MANAGER = 'sk_webshop_manager';
    public const CAP_CONTENT_BUILDER = 'content_builder';

    public static function recreateRoles()
    {
        self::removeRoles();
        self::createRoles();
    }

    public static function isContentManagerOnBuilderSite()
    {
        return PluginStatus::isProductXEnabled() && self::isBuilderPage() && self::isContentManager();
    }

    /**
     * Create roles and capabilities.
     */
    public static function createRoles()
    {
        global $wp_roles;

        if (!class_exists('WP_Roles')) {
            return;
        }

        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        }

        _x('StoreKeeper Webshop manager', 'User role', I18N::DOMAIN);

        // Shop manager role.
        add_role(
            self::ROLE_WEBSHOP_MANAGER,
            'StoreKeeper Webshop manager',
            [
                'level_9' => true,
                'level_8' => true,
                'level_7' => true,
                'level_6' => true,
                'level_5' => true,
                'level_4' => true,
                'level_3' => true,
                'level_2' => true,
                'level_1' => true,
                'level_0' => true,
                'read' => true,
                'read_private_pages' => true,
                'read_private_posts' => true,
                'edit_posts' => true,
                'edit_pages' => true,
                'edit_published_posts' => true,
                'edit_published_pages' => true,
                'edit_private_pages' => true,
                'edit_private_posts' => true,
                'edit_others_posts' => true,
                'edit_others_pages' => true,
                'publish_posts' => true,
                'publish_pages' => true,
                'delete_posts' => true,
                'delete_pages' => true,
                'delete_private_pages' => true,
                'delete_private_posts' => true,
                'delete_published_pages' => true,
                'delete_published_posts' => true,
                'delete_others_posts' => true,
                'delete_others_pages' => true,
                'manage_categories' => true,
                'manage_links' => true,
                'moderate_comments' => true,
                'upload_files' => true,
                'export' => true,
                'import' => true,
                'edit_theme_options' => true,
                self::CAP_CONTENT_BUILDER => true, // my custom
            ]
        );
    }

    public static function removeRoles()
    {
        global $wp_roles;

        if (!class_exists('WP_Roles')) {
            return;
        }

        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
        }

        remove_role(self::ROLE_WEBSHOP_MANAGER);
    }

    public static function isContentManager(): bool
    {
        $isContentManager = false;
        $current_user = wp_get_current_user();
        if ($current_user) {
            $roles = (array) $current_user->roles;
            $isContentManager = in_array(self::ROLE_WEBSHOP_MANAGER, $roles);
        }

        return $isContentManager;
    }

    protected static function isBuilderPage(): bool
    {
        global $pagenow;
        $isBuilder = 'admin.php' == $pagenow && isset($_GET['page']) && 'wopb-settings' == $_GET['page'];

        return $isBuilder;
    }
}
