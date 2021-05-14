<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\Task;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelDeleteCommand;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;

class TaskDelete extends AbstractModelDeleteCommand
{
    public static function getCommandName(): string
    {
        return 'task delete';
    }

    public function getModelClass(): string
    {
        return TaskModel::class;
    }
}
