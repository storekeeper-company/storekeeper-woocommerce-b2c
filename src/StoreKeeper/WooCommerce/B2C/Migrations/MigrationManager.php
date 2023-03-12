<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Exceptions\MigrationExeption;
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

    /**
     * @param DatabaseConnection $connection
     */
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
        $logger->debug('Expected migrations', ['versions' => $versions, 'plugin' => STOREKEEPER_WOOCOMMERCE_B2C_VERSION]);
        $toExecute = MigrationVersionModel::findNotExecutedMigrations($versions);

        if (!empty($toExecute)) {
            $logger->notice('Executing migrations', ['versions' => $toExecute, 'plugin' => STOREKEEPER_WOOCOMMERCE_B2C_VERSION]);
            foreach ($toExecute as $executeId) {
                if (!array_key_exists($executeId, $versions)) {
                    throw new MigrationExeption("Version with id=$executeId not found");
                }
                $class = $versions[$executeId];
                $obj = new $class();
                $this->migrateOne($executeId, $obj);
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
                'log' => $log,
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
