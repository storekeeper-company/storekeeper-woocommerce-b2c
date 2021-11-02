<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use ReflectionClass;
use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\TableRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use WC_REST_System_Status_V2_Controller;

class StatusTab extends AbstractTab
{
    const DEVELOPER_OPTIONS = [
        StoreKeeperOptions::GUEST_AUTH,
        StoreKeeperOptions::SYNC_AUTH,
        WooCommerceOptions::WOOCOMMERCE_TOKEN,
    ];

    const REQUIRED_PHP_EXTENSION = [
        'bcmath',
        'json',
        'mbstring',
        'mysqli',
        'openssl',
        'zip',
    ];

    protected function getStylePaths(): array
    {
        return [
            plugin_dir_url(__FILE__).'/../../../static/status.tab.css',
        ];
    }

    public function render(): void
    {
        $this->renderServerStatus();

        $this->renderDatabaseStatus();

        $this->renderStoreKeeperOptions();
    }

    private function renderStoreKeeperOptions()
    {
        $table = new TableRenderer();
        $table->addColumn(__('StoreKeeper options', I18N::DOMAIN), 'title');
        $table->addColumn('', 'value');
        $table->setData($this->getStoreKeeperOptionData());
        $table->render();
    }

    private function renderServerStatus()
    {
        $table = new TableRenderer();
        $table->addColumn(__('Server status', I18N::DOMAIN), 'title');
        $table->addColumn('', 'value');
        $table->setData($this->getServerStatusData());
        $table->render();
    }

    private function renderDatabaseStatus()
    {
        $table = new TableRenderer();
        $table->addColumn(__('Database status', I18N::DOMAIN), 'title');
        $table->addColumn('', 'value');
        $table->setData($this->getDatabaseStatusData());
        $table->render();
    }

    private function getStoreKeeperOptionData(): array
    {
        $data = [];

        $data = array_merge($data, $this->getOptionClassData(StoreKeeperOptions::class));

        $data = array_merge($data, $this->getOptionClassData(WooCommerceOptions::class));

        $data = array_merge(
            $data,
            $this->getOptionClassData(FeaturedAttributeOptions::class, 'getAttribute')
        );

        return $data;
    }

    private function getOptionClassData(string $classPath, string $getter = 'get')
    {
        $data = [];

        $reflection = new ReflectionClass($classPath);
        foreach ($reflection->getConstants() as $constant) {
            if (!Core::isDebug() && in_array($constant, self::DEVELOPER_OPTIONS)) {
                continue;
            }
            if (is_string($constant)) {
                $value = (new $classPath())::$getter($constant);
                if (null !== $value) {
                    $data[] = [
                        'title' => $constant,
                        'value' => is_array($value) ? json_encode($value) : $value,
                    ];
                }
            }
        }

        return $data;
    }

    private function getServerStatusData(): array
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
            'function::value' => [$this, 'renderCheck'],
        ];

        $extensions = get_loaded_extensions();
        foreach (self::REQUIRED_PHP_EXTENSION as $wantedExtension) {
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
                'function::value' => [$this, 'renderCheck'],
            ];
        }

        return $data;
    }

    private function getDatabaseStatusData(): array
    {
        global $wpdb;

        $data = [];
        $database = (new WC_REST_System_Status_V2_Controller())->get_database_info();

        $data[] = [
            'title' => __('StoreKeeper database version', I18N::DOMAIN),
            'value' => StoreKeeperOptions::get(
                StoreKeeperOptions::INSTALLED_VERSION,
                STOREKEEPER_WOOCOMMERCE_B2C_VERSION
            ),
        ];

        $data[] = [
            'title' => __('Database prefix', I18N::DOMAIN),
            'value' => $wpdb->prefix,
        ];

        $size = $database['database_size'];
        $dataSize = (float) $size['data'];
        $indexSize = (float) $size['index'];
        $data[] = [
            'title' => __('Total database size', I18N::DOMAIN),
            'value' => sprintf('%.2fMB', $dataSize + $indexSize),
        ];
        $data[] = [
            'title' => __('Data database size', I18N::DOMAIN),
            'value' => sprintf('%.2fMB', $dataSize),
        ];
        $data[] = [
            'title' => __('Index database size', I18N::DOMAIN),
            'value' => sprintf('%.2fMB', $indexSize),
        ];

        $tables = array_filter(
            $database['database_tables']['other'],
            function ($key) {
                return false !== strpos($key, 'storekeeper');
            },
            ARRAY_FILTER_USE_KEY
        );
        foreach ($tables as $table => $info) {
            $data[] = [
                'title' => $table,
                'value' => sprintf(
                    __('Data: %.2fMB | Index: %.2fMB | Engine: %s', I18N::DOMAIN),
                    $info['data'],
                    $info['index'],
                    $info['engine']
                ),
            ];
        }

        return $data;
    }

    public function renderCheck($value, $item)
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
}
