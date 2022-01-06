<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelGetCommand;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;

class WebhookLogGet extends AbstractModelGetCommand
{
    public static function getShortDescription(): string
    {
        return __('Retrieve webhook log.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Retrieve a webhook log by the specified ID.', I18N::DOMAIN);
    }

    public static function getCommandName(): string
    {
        return 'webhook-log get';
    }

    public function getModelClass(): string
    {
        return WebhookLogModel::class;
    }
}
