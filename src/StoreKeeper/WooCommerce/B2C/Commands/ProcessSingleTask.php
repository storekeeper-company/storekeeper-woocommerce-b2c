<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use Exception;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class ProcessSingleTask extends AbstractCommand
{
    public static function needsFullWpToExecute(): bool
    {
        return true;
    }

    /**
     * @return mixed|void
     *
     * @throws Exception
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        $this->setupApi();

        $task_id = $arguments[0];
        $task = TaskModel::get($task_id);
        $this->logger->info(
            'Got task',
            [
                'task_id' => $task_id,
            ]
        );
        if (null === $task) {
            throw new Exception("Could not find task with id '{$task_id}'");
        }

        $handler = new TaskHandler();
        $handler->setLogger($this->logger);
        $handler->handleImport($task['name']);
        $this->logger->info(
            'Task done',
            [
                'post_id' => $task_id,
            ]
        );

        // Add the removed tasks to the current task
        $task['meta_data']['removed_task_ids'] = $handler->getTrashedTasks();
        TaskModel::update($task_id, $task);
    }
}
