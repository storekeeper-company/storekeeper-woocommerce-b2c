<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\Task;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelPurgeCommand;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;

class TaskPurge extends AbstractModelPurgeCommand
{
    public static function getCommandName(): string
    {
        return 'task purge';
    }

    public function getModelClass(): string
    {
        return TaskModel::class;
    }
}
