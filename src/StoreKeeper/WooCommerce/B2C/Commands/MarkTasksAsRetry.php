<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class MarkTasksAsRetry extends AbstractMarkTasksAs
{
    public static function getShortDescription(): string
    {
        return sprintf(
            __('Mark tasks to "%s" status.', I18N::DOMAIN),
            TaskHandler::STATUS_NEW
        );
    }

    public static function getLongDescription(): string
    {
        return sprintf(
            __('Mark selected tasks to "%s" status.', I18N::DOMAIN),
            TaskHandler::STATUS_NEW
        );
    }

    public static function getCommandName(): string
    {
        return 'task mark-as-retry';
    }

    protected function getDesiredStatus()
    {
        return TaskHandler::STATUS_NEW;
    }
}
