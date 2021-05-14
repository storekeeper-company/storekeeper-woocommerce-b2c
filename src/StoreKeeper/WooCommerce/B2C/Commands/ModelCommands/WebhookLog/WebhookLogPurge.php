<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelPurgeCommand;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;

class WebhookLogPurge extends AbstractModelPurgeCommand
{
    public static function getCommandName(): string
    {
        return 'webhook-log purge';
    }

    public function getModelClass(): string
    {
        return WebhookLogModel::class;
    }
}
