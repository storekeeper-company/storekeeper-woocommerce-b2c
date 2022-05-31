<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use ReflectionClass;
use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\TableRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Notices\AdminNotices;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\AbstractModel;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeExportOptions;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use WC_REST_System_Status_V2_Controller;

class StatusTab extends AbstractTab
{
    use FormElementTrait;

    const TABLES_IN_INNODB = [
        'terms',
    ];

    const ACTION_SET_INNO_DB = 'set-inno-db';

    const DEVELOPER_OPTIONS = [
        StoreKeeperOptions::GUEST_AUTH,
        StoreKeeperOptions::SYNC_AUTH,
        WooCommerceOptions::WOOCOMMERCE_TOKEN,
    ];

    protected function getStylePaths(): array
    {
        return [
            plugin_dir_url(__FILE__).'/../../../static/status.tab.css',
        ];
    }

    public function __construct(string $title, string $slug = '')
    {
        parent::__construct($title, $slug);

        $this->addAction(self::ACTION_SET_INNO_DB, [$this, 'setInnoDb']);
    }

    public function render(): void
    {
        $this->renderServerStatus();
        $this->renderInnodbStatus();
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

    private function renderInnodbStatus()
    {
        $table = new TableRenderer('table-innodb');
        $table->addColumn(__('Tables are using InnoDB engine', I18N::DOMAIN), 'title');
        $table->addColumn('', 'value');
        $table->setData($this->getInnoDBStatusData());
        $table->render();
    }

    private function getStoreKeeperOptionData(): array
    {
        $data = [];

        $data = array_merge($data, $this->getOptionClassData(StoreKeeperOptions::class));

        $data = array_merge($data, $this->getOptionClassData(WooCommerceOptions::class));

        $data = array_merge(
            $data,
            $this->getOptionClassData(FeaturedAttributeExportOptions::class, 'getAttributeExportOptionConstant')
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
            'function::value' => [$this, 'renderCheckWithValue'],
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

    private function getInnoDBStatusData(): array
    {
        global $wpdb;

        $data = [];
        foreach (self::TABLES_IN_INNODB as $table) {
            $tableName = $wpdb->prefix.$table;
            $data[] = [
                'title' => $tableName,
                'value' => AbstractModel::isTableEngineInnoDB($tableName),
                'function::value' => function ($value, $item) use ($tableName) {
                    $this->renderInnoDbCheck($value, $item, $tableName);
                },
                'description' => sprintf(
                    __(
                        'The %s table needs to be using Engine=InnoDB in order to use the plugin',
                        I18N::DOMAIN
                    ),
                    $tableName
                ),
            ];
        }

        return $data;
    }

    public function renderInnoDbCheck($value, $item, $tableName)
    {
        $this->renderCheck($value, $item);
        if (!$value) {
            $this->renderFormStart();
            $this->renderRequestHiddenInputs();
            $this->renderFormHiddenInput('action', self::ACTION_SET_INNO_DB);
            $this->renderFormActionGroup(
                $this->getFormButton(
                    __('Set InnoDb  on this table', I18N::DOMAIN),
                    'button',
                    'table',
                    $tableName
                )
            );

            $this->renderFormEnd();
        }
    }

    public function setInnoDb()
    {
        $tableName = sanitize_key($_REQUEST['table']);
        AbstractModel::setTableEngineToInnoDB($tableName);
        AdminNotices::showSuccess(sprintf(
            __(
                'The %s table was set to Engine=InnoDB.',
                I18N::DOMAIN
            ),
            $tableName
        ), );
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

    public function renderCheckWithValue($value, $item)
    {
        $this->renderCheck($value, $item);
        if ($value) {
            echo esc_html($value);
        }
    }
}
