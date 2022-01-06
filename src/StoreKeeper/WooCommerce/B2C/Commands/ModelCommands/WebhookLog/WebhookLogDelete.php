<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelDeleteCommand;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;

class WebhookLogDelete extends AbstractModelDeleteCommand
{
    public static function getShortDescription(): string
    {
        return __('Delete webhook log.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Delete a webhook log by the specified ID.', I18N::DOMAIN);
    }

    public static function getCommandName(): string
    {
        return 'webhook-log delete';
    }

    public function getModelClass(): string
    {
        return WebhookLogModel::class;
    }
}
