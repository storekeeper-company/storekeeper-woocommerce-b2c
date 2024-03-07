<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class ProcessSingleTask extends AbstractCommand
{
    public static function getShortDescription(): string
    {
        return __('Process a single task by providing task ID.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Process a single task at any given status by providing task ID.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'positional',
                'name' => 'task-id',
                'description' => __('The ID of the task to be processed.', I18N::DOMAIN),
                'optional' => false,
            ],
        ];
    }

    /**
     * @return mixed|void
     *
     * @throws \Exception
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        $preExecutionMicroTime = microtime(true);
        $preExecutionDateTime = DatabaseConnection::formatToDatabaseDate();
        $this->setupApi();

        $task_id = $arguments[0];
        $task = TaskModel::get($task_id);
        $this->logger->info('Got task', ['task_id' => $task_id]);

        if (null === $task) {
            throw new \Exception("Could not find task with id '{$task_id}'");
        }

        $handler = new TaskHandler();
        $handler->setLogger($this->logger);
        $taskResult = $handler->handleTask($task_id, $task['name']);
        $this->logger->info('Task done', ['id' => $task_id, 'result' => $taskResult]);

        // Add the removed tasks to the current task
        $task['meta_data']['removed_task_ids'] = $handler->getTrashedTasks();

        $postExecutionMicroTime = microtime(true);
        $postExecutionDateTime = DatabaseConnection::formatToDatabaseDate();
        $executionDuration = $postExecutionMicroTime - $preExecutionMicroTime;

        $task['meta_data']['pre_execution'] = $preExecutionDateTime;
        $task['meta_data']['post_execution'] = $postExecutionDateTime;
        $task['date_last_processed'] = $postExecutionDateTime;
        $task['execution_duration'] = $executionDuration;
        TaskModel::update($task_id, $task);
    }
}
