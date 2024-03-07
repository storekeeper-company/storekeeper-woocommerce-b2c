<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\I18N;

class ServerStatusChecker
{
    public const REQUIRED_PHP_EXTENSION = [
        'bcmath',
        'json',
        'mbstring',
        'mysqli',
        'openssl',
        'zip',
    ];

    public const OPTIONAL_PHP_EXTENSION = [
        'posix',
    ];

    public static function getServerIssues(): array
    {
        $serverIssues = [];

        if (phpversion() < STOREKEEPER_WOOCOMMERCE_B2C_PHP_VERSION) {
            $serverIssues[] = [
                'name' => sprintf(
                    __('PHP version %s or up', I18N::DOMAIN),
                    STOREKEEPER_WOOCOMMERCE_B2C_PHP_VERSION
                ),
                'level' => 'error',
                'error' => sprintf(
                    __(
                        'PHP version %s found, plugin requires at least version %s',
                        I18N::DOMAIN
                    ),
                    phpversion(),
                    STOREKEEPER_WOOCOMMERCE_B2C_PHP_VERSION
                ),
            ];
        }

        if (!wc_product_sku_enabled()) {
            $serverIssues[] = [
                'name' => __('WooCommerce SKU feature enabled', I18N::DOMAIN),
                'level' => 'warning',
                'error' => __('SKU feature for WooCommerce has been disabled.', I18N::DOMAIN),
            ];
        }

        if ('yes' !== get_option('woocommerce_manage_stock')) {
            $serverIssues[] = [
                'name' => __('WooCommerce stock management feature enabled', I18N::DOMAIN),
                'level' => 'warning',
                'error' => __('WooCommerce stock management feature has been disabled.', I18N::DOMAIN),
            ];
        }

        $extensions = get_loaded_extensions();
        foreach (static::REQUIRED_PHP_EXTENSION as $wantedExtension) {
            if (!in_array($wantedExtension, $extensions)) {
                $serverIssues[] = [
                    'name' => sprintf(__('PHP %s extension', I18N::DOMAIN), $wantedExtension),
                    'level' => 'error',
                    'error' => sprintf(
                        __(
                            'Enabling PHP %s extension is required',
                            I18N::DOMAIN
                        ),
                        $wantedExtension
                    ),
                ];
            }
        }

        foreach (static::OPTIONAL_PHP_EXTENSION as $wantedExtension) {
            if (!in_array($wantedExtension, $extensions)) {
                $serverIssues[] = [
                    'name' => sprintf(__('PHP %s extension', I18N::DOMAIN), $wantedExtension),
                    'level' => 'warning',
                    'error' => sprintf(
                        __(
                            'Enabling PHP %s extension is optional to improve performance',
                            I18N::DOMAIN
                        ),
                        $wantedExtension
                    ),
                ];
            }
        }

        return $serverIssues;
    }

    public static function getServerStatusData(): array
    {
        $data = [];

        $data[] = [
            'title' => sprintf(
                __('PHP version %s or up', I18N::DOMAIN),
                STOREKEEPER_WOOCOMMERCE_B2C_PHP_VERSION
            ),
            'description' => sprintf(
                __(
                    'Contact your server provider to upgrade your PHP version to at least %s',
                    I18N::DOMAIN
                ),
                STOREKEEPER_WOOCOMMERCE_B2C_PHP_VERSION
            ),
            'value' => phpversion() >= STOREKEEPER_WOOCOMMERCE_B2C_PHP_VERSION,
            'function::value' => function ($value, $item) {
                self::renderCheck($value, $item);
            },
        ];
        $data[] = [
            'title' => __('Writable tmp directory', I18N::DOMAIN),
            'description' => sprintf(
                __(
                    'Contact your server provider to allow one of the those directories to be writable: %s',
                    I18N::DOMAIN
                ),
                implode(', ', Core::getPossibleTmpDirs())
            ),
            'value' => Core::getTmpBaseDir(),
            'function::value' => function ($value, $item) {
                self::renderCheck($value, $item);
                echo $value;
            },
        ];

        $data[] = [
            'title' => __('WooCommerce SKU feature enabled', I18N::DOMAIN),
            'description' => __('SKU feature for WooCommerce has been disabled.', I18N::DOMAIN),
            'value' => wc_product_sku_enabled(),
            'function::value' => function ($value, $item) {
                self::renderCheck($value, $item);
            },
        ];

        $data[] = [
            'title' => __('WooCommerce stock management feature enabled', I18N::DOMAIN),
            'description' => __('WooCommerce stock management feature has been disabled.', I18N::DOMAIN),
            'value' => 'yes' === get_option('woocommerce_manage_stock'),
            'function::value' => function ($value, $item) {
                self::renderCheck($value, $item);
            },
        ];

        $extensions = get_loaded_extensions();
        foreach (static::REQUIRED_PHP_EXTENSION as $wantedExtension) {
            $data[] = [
                'title' => sprintf(__('PHP %s extension', I18N::DOMAIN), $wantedExtension),
                'description' => sprintf(
                    __(
                        'Contact your server provider to enable the PHP %s extension for the StoreKeeper synchronization plugin to function properly',
                        I18N::DOMAIN
                    ),
                    $wantedExtension
                ),
                'value' => in_array($wantedExtension, $extensions),
                'function::value' => function ($value, $item) {
                    self::renderCheck($value, $item);
                },
            ];
        }
        foreach (static::OPTIONAL_PHP_EXTENSION as $wantedExtension) {
            $data[] = [
                'title' => sprintf(__('PHP %s extension', I18N::DOMAIN), $wantedExtension),
                'description' => sprintf(
                    __(
                        'Contact your server provider to enable the PHP %s extension to improve the performance and stability',
                        I18N::DOMAIN
                    ),
                    $wantedExtension
                ),
                'value' => in_array($wantedExtension, $extensions),
                'function::value' => function ($value, $item) {
                    self::renderCheck($value, $item);
                },
            ];
        }

        return $data;
    }

    public static function renderCheck($value, $item): void
    {
        $html = '<span class="dashicons dashicons-yes text-success"></span>';
        if (!$value) {
            $description = esc_html($item['description']) ?? '';
            $html = <<<HTML
<span class="text-danger">
    <span class="dashicons dashicons-warning"></span>
    $description
</span>
HTML;
        }

        echo $html;
    }

    public static function renderCheckWithValue($value, $item): void
    {
        self::renderCheck($value, $item);
        if ($value) {
            echo esc_html($value);
        }
    }
}
