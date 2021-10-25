<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\ExportSettingsTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\ExportTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\LogPurgerTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\PluginConflictCheckerTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\SynCheckTab;
use StoreKeeper\WooCommerce\B2C\I18N;

class ToolsPage extends AbstractPage
{
    protected function getTabs(): array
    {
        return [
            new SynCheckTab(__('Synchronize from StoreKeeper', I18N::DOMAIN)),
            new PluginConflictCheckerTab(__('Plugin conflict checker', I18N::DOMAIN), 'plugin-conflicts'),
            new ExportTab(__('One Time Export', I18N::DOMAIN), 'export'),
            new ExportSettingsTab(__('One Time Export settings', I18N::DOMAIN), ExportSettingsTab::SLUG),
            new LogPurgerTab(__('Log purger', I18N::DOMAIN), 'the-purge'),
        ];
    }
}
