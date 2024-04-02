<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\Tools\TaskMarkingTrait;

abstract class AbstractTaskRetryMigration extends AbstractMigration
{
    use TaskMarkingTrait;

    final public function up(DatabaseConnection $connection): ?string
    {
        $task_ids = $this->getTaskIdsForMigration($connection);

        if (!empty($task_ids)) {
            $this->markTasks($connection, $task_ids, TaskHandler::STATUS_NEW);

            return 'Task id: '.implode(',', $task_ids);
        }

        return null;
    }

    /**
     * Overwrite this function to get different tasks for retry.
     *
     * @throws \Exception
     */
    protected function getTaskIdsForMigration(DatabaseConnection $connection): array
    {
        return $this->getTaskIds($connection, TaskHandler::STATUS_FAILED);
    }
}
