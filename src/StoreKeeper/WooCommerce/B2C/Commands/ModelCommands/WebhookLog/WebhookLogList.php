<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelListCommand;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;

class WebhookLogList extends AbstractModelListCommand
{
    public static function getShortDescription(): string
    {
        return __('Retrieve all webhook log.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Retrieve all webhook log from the database.', I18N::DOMAIN);
    }

    public static function getCommandName(): string
    {
        return 'webhook-log list';
    }

    public function getModelClass(): string
    {
        return WebhookLogModel::class;
    }
}
