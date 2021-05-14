<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelListCommand;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;

class WebhookLogList extends AbstractModelListCommand
{
    public static function getCommandName(): string
    {
        return 'webhook-log list';
    }

    public function getModelClass(): string
    {
        return WebhookLogModel::class;
    }
}
