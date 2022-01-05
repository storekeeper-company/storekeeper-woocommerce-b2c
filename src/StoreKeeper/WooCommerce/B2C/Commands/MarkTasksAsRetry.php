<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class MarkTasksAsRetry extends AbstractMarkTasksAs
{
    public static function getShortDescription(): string
    {
        return __('Mark tasks to "status-new" status.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Mark selected tasks to "status-new" status', I18N::DOMAIN);
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
