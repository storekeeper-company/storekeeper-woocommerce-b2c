<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\BackofficeRolesTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\ConnectionTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\DeveloperSettingsTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\SchedulerTab;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\I18N;

class SettingsPage extends AbstractPage
{
    protected function getTabs(): array
    {
        $tabs = [];
        $tabs[] = new ConnectionTab(__('Connection', I18N::DOMAIN));
        $tabs[] = new SchedulerTab(__('Scheduler settings', I18N::DOMAIN), 'scheduler');
        if (Core::isDebug()) {
            $tabs[] = new DeveloperSettingsTab(__('Developer settings', I18N::DOMAIN), 'developer-settings');
        }
        $tabs[] = new BackofficeRolesTab(__('Backoffice roles', I18N::DOMAIN), 'backoffice-roles');

        return $tabs;
    }
}
