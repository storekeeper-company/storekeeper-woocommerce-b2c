<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\Task;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelGetCommand;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;

class TaskGet extends AbstractModelGetCommand
{
    public static function getShortDescription(): string
    {
        return __('Retrieve task.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Retrieve a task by the specified ID.', I18N::DOMAIN);
    }

    public static function getCommandName(): string
    {
        return 'task get';
    }

    public function getModelClass(): string
    {
        return TaskModel::class;
    }
}
