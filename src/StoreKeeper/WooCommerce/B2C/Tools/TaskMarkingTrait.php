<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;

trait TaskMarkingTrait
{
    protected function markTasks(DatabaseConnection $connection, array $task_ids, string $desired_status): void
    {
        $in = "'".implode("','", $task_ids)."'";
        $update = TaskModel::getUpdateHelper()
            ->cols([
                'status' => $desired_status,
                'date_updated' => DatabaseConnection::formatToDatabaseDate(),
            ])
            ->where("id IN ($in)");

        $query = $connection->prepare($update);
        $connection->querySql($query);
    }

    final protected function getTaskIds(
        DatabaseConnection $connection,
        string $status,
        ?string $type = null
    ): array {
        if (!in_array($status, TaskHandler::STATUSES)) {
            throw new \Exception('Only allowed statuses are '.implode(', ', TaskHandler::STATUSES));
        }

        $select = TaskModel::getSelectHelper()
            ->cols(['id'])
            ->where('status = :status')
            ->bindValue('status', $status);
        if (!is_null($type)) {
            if (!in_array($type, TaskHandler::TYPE_GROUPS)) {
                throw new \Exception('type should be one of '.implode(',', TaskHandler::TYPE_GROUPS));
            }
            $select
                ->where('type_group = :type_group')
                ->bindValue('type_group', $type);
        }
        $select
            ->where('type_group != :not_report')
            ->bindValue('not_report', TaskHandler::REPORT_ERROR_TYPE_GROUP);

        $query = $connection->prepare($select);
        $results = $connection->querySql($query)->fetch_all();

        $taskIds = [];
        foreach ($results as $result) {
            $taskIds[] = intval(current($result));
        }

        return $taskIds;
    }
}
