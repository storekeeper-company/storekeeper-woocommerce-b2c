<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\TaskLogsTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\WebhookLogsTab;
use StoreKeeper\WooCommerce\B2C\I18N;

class LogsPage extends AbstractPage
{
    protected function getTabs(): array
    {
        return [
            new TaskLogsTab(
                __('Tasks', I18N::DOMAIN)
            ),
            new WebhookLogsTab(
                __('Webhooks', I18N::DOMAIN),
                'webhook'
            ),
        ];
    }
}
