<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Models\MigrationVersionModel;

class MigrationManager
{
    /**
     * @var DatabaseConnection
     */
    protected $connection;
    /**
     * @var VersionsInterface
     */
    protected $versions;

    public function __construct(?VersionsInterface $versions = null)
    {
        $this->connection = new DatabaseConnection();
        $this->versions = $versions ?? new AllVersions();
    }

    public function migrateAll(): int
    {
        $this->ensureMigrationTable();

        $logger = LoggerFactory::create('migrations');
        $versions = $this->versions->getVersionsById();

        $existingIds = MigrationVersionModel::getAllMigrations(array_keys($versions));
        $toExecute = array_diff_key($versions, array_flip($existingIds));

        $logger->debug('Expected migrations', [
            'versions' => $versions,
            'existingIds' => $existingIds,
            'plugin' => STOREKEEPER_WOOCOMMERCE_B2C_VERSION,
        ]);

        if (!empty($toExecute)) {
            $logger->notice('Executing migrations', ['versions' => $toExecute, 'plugin' => STOREKEEPER_WOOCOMMERCE_B2C_VERSION]);
            foreach ($toExecute as $executeId => $class) {
                try {
                    $class = $versions[$executeId];
                    $obj = new $class();
                    $this->migrateOne($executeId, $obj);
                    $logger->info('Migrated', [
                        'id' => $executeId,
                        'plugin' => STOREKEEPER_WOOCOMMERCE_B2C_VERSION,
                        'class' => $class,
                    ]);
                } catch (\Throwable $e) {
                    $logger->error('Migration failed',
                        [
                            'id' => $executeId,
                            'plugin' => STOREKEEPER_WOOCOMMERCE_B2C_VERSION,
                            'class' => $class,
                            'e' => $e->getMessage(),
                            'e_class' => get_class($e),
                            'e_trace' => $e->getTraceAsString(),
                        ]
                    );
                    throw $e;
                }
            }
        } else {
            $logger->info('All is migrated', ['versions' => $toExecute, 'plugin' => STOREKEEPER_WOOCOMMERCE_B2C_VERSION]);
        }

        return count($toExecute);
    }

    protected function migrateOne(int $id, AbstractMigration $migration)
    {
        $log = $migration->up($this->connection);

        MigrationVersionModel::create(
            [
                'id' => $id,
                'plugin_version' => STOREKEEPER_WOOCOMMERCE_B2C_VERSION,
                'log' => $log ?? 'OK',
                'class' => get_class($migration),
            ]
        );
    }

    public function ensureMigrationTable()
    {
        if (!MigrationVersionModel::hasTable()) {
            $this->connection->querySql(MigrationVersionModel::getCreateSql());
        }
    }
}
