<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use Exception;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Exceptions\LockActiveException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use Throwable;

abstract class AbstractMarkTasksAs extends AbstractCommand
{
    const ALLOWED_TASK_STATUSES = [TaskHandler::STATUS_PROCESSING, TaskHandler::STATUS_FAILED, TaskHandler::STATUS_NEW];
    /**
     * @var DatabaseConnection
     */
    protected $db;

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'assoc',
                'name' => 'select_status',
                'description' => __('The status of tasks to be marked.', I18N::DOMAIN),
                'optional' => true,
                'default' => TaskHandler::STATUS_FAILED,
                'options' => self::ALLOWED_TASK_STATUSES,
            ],
            [
                'type' => 'assoc',
                'name' => 'select_type',
                'description' => __('The type of tasks to be marked.', I18N::DOMAIN),
                'optional' => true,
                'default' => null,
                'options' => TaskHandler::TYPE_GROUPS,
            ],
        ];
    }

    /**
     * @return void
     *
     * @throws Throwable
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        $desired_status = $this->getDesiredStatus();

        try {
            $this->lock();
            $this->db = new DatabaseConnection();
            $this->logger->debug(
                'Connected to DB',
                [
                    'host' => DB_HOST,
                    'user' => DB_USER,
                    'db' => DB_NAME,
                ]
            );

            $select_status = $assoc_arguments['select_status'] ?? TaskHandler::STATUS_FAILED;
            $select_type = $assoc_arguments['select_type'] ?? null;

            if ($select_status === $desired_status) {
                throw new Exception('Selected status is same as desired status');
            }

            $task_ids = $this->getTaskIds($select_status, $select_type);
            $task_quantity = count($task_ids);
            $this->logger->info(
                'Tasks count to mark as '.$desired_status,
                [
                    'total' => $task_quantity,
                    'select_status' => $select_status,
                    'select_type' => $select_type,
                ]
            );

            if ($task_quantity > 0) {
                \WP_CLI::confirm("Are you sure you want to mark {$task_quantity} tasks as $desired_status?");

                $this->markTasks($task_ids, $desired_status);

                \WP_CLI::success("Marked {$task_quantity} tasks as $desired_status");
            } else {
                $select_type = $select_type ?? 'any';
                \WP_CLI::success("No tasks found for select_type=$select_type and select_status=$select_status");
            }
        } catch (LockActiveException $exception) {
            $this->logger->notice('Cannot run. lock on.');
        }
    }

    /**
     * @return int[]
     *
     * @throws Exception
     */
    protected function getTaskIds(
        string $status,
        string $type = null
    ): array {
        if (!in_array($status, self::ALLOWED_TASK_STATUSES)) {
            throw new Exception('Only allowed statuses are '.implode(', ', self::ALLOWED_TASK_STATUSES));
        }

        $select = TaskModel::getSelectHelper()
            ->cols(['id'])
            ->where('status = :status')
            ->bindValue('status', $status);
        if (!is_null($type)) {
            if (!in_array($type, TaskHandler::TYPE_GROUPS)) {
                throw new Exception('type should be one of '.implode(',', TaskHandler::TYPE_GROUPS));
            }
            $select
                ->where('type_group = :type_group')
                ->bindValue('type_group', $type);
        }

        $query = $this->db->prepare($select);
        $results = $this->db->querySql($query)->fetch_all();

        $taskIds = [];
        foreach ($results as $result) {
            $taskIds[] = intval(current($result));
        }

        return $taskIds;
    }

    abstract protected function getDesiredStatus();

    protected function markTasks(array $task_ids, string $desired_status): void
    {
        $in = "'".implode("','", $task_ids)."'";
        $update = TaskModel::getUpdateHelper()
            ->cols(['status' => $desired_status])
            ->where("id IN ($in)");

        $query = $this->db->prepare($update);
        $this->db->querySql($query);
    }
}
