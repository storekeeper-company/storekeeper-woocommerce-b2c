<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelDeleteCommand;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;

class WebhookLogDelete extends AbstractModelDeleteCommand
{
    public static function getCommandName(): string
    {
        return 'webhook-log delete';
    }

    public function getModelClass(): string
    {
        return WebhookLogModel::class;
    }
}
