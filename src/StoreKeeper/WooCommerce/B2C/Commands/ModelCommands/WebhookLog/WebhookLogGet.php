<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelGetCommand;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;

class WebhookLogGet extends AbstractModelGetCommand
{
    public static function getCommandName(): string
    {
        return 'webhook-log get';
    }

    public function getModelClass(): string
    {
        return WebhookLogModel::class;
    }
}
