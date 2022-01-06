<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\Task;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelPurgeCommand;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;

class TaskPurge extends AbstractModelPurgeCommand
{
    public static function getShortDescription(): string
    {
        return __('Purge tasks.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Purge successful tasks but only purges items older than 30 days or if there are more than 1000 items, we purge all but the last 1000 items and purge items older than 7 days.', I18N::DOMAIN);
    }

    public static function getCommandName(): string
    {
        return 'task purge';
    }

    public function getModelClass(): string
    {
        return TaskModel::class;
    }
}
