<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\Task;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelGetCommand;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;

class TaskGet extends AbstractModelGetCommand
{
    public static function getCommandName(): string
    {
        return 'task get';
    }

    public function getModelClass(): string
    {
        return TaskModel::class;
    }
}
