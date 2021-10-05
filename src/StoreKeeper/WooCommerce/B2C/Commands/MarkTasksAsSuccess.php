<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class MarkTasksAsSuccess extends AbstractMarkTasksAs
{
    public static function getCommandName(): string
    {
        return 'task mark-as-success';
    }

    protected function getDesiredStatus()
    {
        return TaskHandler::STATUS_SUCCESS;
    }
}
