<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\WebhookLog;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelPurgeCommand;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;

class WebhookLogPurge extends AbstractModelPurgeCommand
{
    public static function getShortDescription(): string
    {
        return __('Purge webhook logs.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Purge webhook logs but only purges items older than 30 days or if there are more than 1000 items, we purge all but the last 1000 items and purge items older than 7 days.', I18N::DOMAIN);
    }

    public static function getCommandName(): string
    {
        return 'webhook-log purge';
    }

    public function getModelClass(): string
    {
        return WebhookLogModel::class;
    }
}
