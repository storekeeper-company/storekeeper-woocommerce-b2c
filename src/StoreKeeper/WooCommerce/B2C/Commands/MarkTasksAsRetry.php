<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class MarkTasksAsRetry extends AbstractMarkTasksAs
{
    public static function getCommandName(): string
    {
        return 'task mark-as-retry';
    }

    protected function getDesiredStatus()
    {
        return TaskHandler::STATUS_NEW;
    }
}
