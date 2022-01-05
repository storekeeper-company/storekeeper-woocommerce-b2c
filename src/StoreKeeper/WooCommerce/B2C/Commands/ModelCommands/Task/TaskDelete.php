<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\Task;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelDeleteCommand;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;

class TaskDelete extends AbstractModelDeleteCommand
{
    public static function getShortDescription(): string
    {
        return __('Delete task.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Delete a task by the specified ID.', I18N::DOMAIN);
    }

    public static function getCommandName(): string
    {
        return 'task delete';
    }

    public function getModelClass(): string
    {
        return TaskModel::class;
    }
}
