<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class MarkTasksAsSuccess extends AbstractMarkTasksAs
{
    public static function getShortDescription(): string
    {
        return __('Mark tasks to "status-success" status.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Mark selected tasks to "status-success" status', I18N::DOMAIN);
    }

    public static function getCommandName(): string
    {
        return 'task mark-as-success';
    }

    protected function getDesiredStatus()
    {
        return TaskHandler::STATUS_SUCCESS;
    }
}
