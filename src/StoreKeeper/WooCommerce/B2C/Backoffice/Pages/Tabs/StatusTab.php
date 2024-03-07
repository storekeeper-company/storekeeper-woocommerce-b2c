<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\TableRenderer;
use StoreKeeper\WooCommerce\B2C\Backoffice\Notices\AdminNotices;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Helpers\ServerStatusChecker;
use StoreKeeper\WooCommerce\B2C\Hooks\WpFilterInterface;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\AbstractModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeOptionModel;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeExportOptions;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;

class StatusTab extends AbstractTab
{
    use FormElementTrait;

    public const TABLES_IN_INNODB = [
        'terms',
    ];

    public const ACTION_SET_INNO_DB = 'set-inno-db';

    public const DEVELOPER_OPTIONS = [
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
        $this->renderAvailableWpHooks();
        $this->renderDatabaseStatus();
        $this->renderTableRelationData();
        $this->renderStoreKeeperOptions();
        $this->renderSetConstants();
    }

    private function renderAvailableWpHooks()
    {
        $table = new TableRenderer();
        $table->addColumn(__('Registered wordpress hooks', I18N::DOMAIN), 'title');
        $table->addColumn('', 'type');
        $table->addColumn('', 'description');

        $data = [];
        foreach (Core::HOOKS as $class) {
            if (is_a($class, WpFilterInterface::class, true)) {
                $data[] = [
                    'title' => $class::getTag(),
                    'type' => 'filter',
                    'description' => $class::getDescription(),
                ];
            }
        }
        $table->setData($data);

        $table->render();
    }

    private function renderStoreKeeperOptions()
    {
        $table = new TableRenderer();
        $table->addColumn(__('StoreKeeper options', I18N::DOMAIN), 'title');
        $table->addColumn('', 'value');
        $table->setData($this->getStoreKeeperOptionData());
        $table->render();
    }

    private function renderSetConstants()
    {
        $table = new TableRenderer();
        $table->addColumn(__('Wp-config settings', I18N::DOMAIN), 'title');
        $table->addColumn('', 'value');
        $table->setData($this->getConstantsData());
        $table->render();
    }

    private function renderServerStatus()
    {
        $table = new TableRenderer();
        $table->addColumn(__('Server status', I18N::DOMAIN), 'title');
        $table->addColumn('', 'value');
        $table->setData(ServerStatusChecker::getServerStatusData());
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

    private function renderTableRelationData()
    {
        $table = new TableRenderer('table-innodb');
        $table->addColumn(__('Table foreign keys and InnoDB', I18N::DOMAIN), 'title');
        $table->addColumn('', 'value');
        $table->setData(array_merge(
            $this->getInnoDBStatusData(),
            $this->getForeignKeyStatusData()
        ));
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

    private function getConstantsData()
    {
        $constants = [
            'WP_DEBUG' => WP_DEBUG,
            'STOREKEEPER_WOOCOMMERCE_B2C_LOG_LEVEL' => STOREKEEPER_WOOCOMMERCE_B2C_LOG_LEVEL,
            'STOREKEEPER_WOOCOMMERCE_B2C_VERSION' => STOREKEEPER_WOOCOMMERCE_B2C_VERSION,
            'STOREKEEPER_WOOCOMMERCE_FILE' => STOREKEEPER_WOOCOMMERCE_FILE,
            'STOREKEEPER_WOOCOMMERCE_INTEGRATIONS' => STOREKEEPER_WOOCOMMERCE_INTEGRATIONS,
            'STOREKEEPER_WOOCOMMERCE_INTEGRATIONS_USE_FLAG' => STOREKEEPER_WOOCOMMERCE_INTEGRATIONS_USE_FLAG,
            'STOREKEEPER_WOOCOMMERCE_B2C_DEBUG' => STOREKEEPER_WOOCOMMERCE_B2C_DEBUG,
            'STOREKEEPER_WOOCOMMERCE_API_DUMP' => STOREKEEPER_WOOCOMMERCE_API_DUMP,
        ];

        $data = [];
        foreach ($constants as $constant => $value) {
            $data[] = [
                'title' => $constant,
                'value' => json_encode($value),
            ];
        }

        return $data;
    }

    private function getOptionClassData(string $classPath, string $getter = 'get')
    {
        $data = [];

        $reflection = new \ReflectionClass($classPath);
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

    private function getDatabaseStatusData(): array
    {
        global $wpdb;

        $data = [];
        $database = (new \WC_REST_System_Status_V2_Controller())->get_database_info();

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
            function ($key) use ($wpdb) {
                return false !== strpos($key, 'storekeeper')
                    && 0 === strpos($key, $wpdb->prefix);
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

    private function getForeignKeyStatusData(): array
    {
        $keys = [];
        $attTable = AttributeModel::getTableName();
        $keys[$attTable][] = AttributeModel::getValidForeignFieldKey('attribute_id_fk', $attTable);
        $attOptTable = AttributeOptionModel::getTableName();
        $keys[$attOptTable][] = AttributeOptionModel::getValidForeignFieldKey('storekeeper_attribute_id_fk', $attOptTable);
        $keys[$attOptTable][] = AttributeOptionModel::getValidForeignFieldKey('term_id_fk', $attOptTable);

        $data = [];
        foreach ($keys as $tableName => $foreignKeys) {
            foreach ($foreignKeys as $foreignKey) {
                $data[] = [
                    'title' => $tableName.'.'.$foreignKey,
                    'value' => AbstractModel::foreignKeyExists($tableName, $foreignKey),
                    'function::value' => function ($value, $item) {
                        ServerStatusChecker::renderCheck($value, $item);
                        if ($value) {
                            echo __('Foreign key constraint exists', I18N::DOMAIN);
                        }
                    },
                    'description' => __('Foreign key constraint is missing', I18N::DOMAIN),
                ];
            }
        }

        return $data;
    }

    public function renderInnoDbCheck($value, $item, $tableName)
    {
        ServerStatusChecker::renderCheck($value, $item);
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
        } else {
            echo esc_html__('Table is using InnoDB engine', I18N::DOMAIN);
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
}
