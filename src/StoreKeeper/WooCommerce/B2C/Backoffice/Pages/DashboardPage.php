<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\DashboardTab;
use StoreKeeper\WooCommerce\B2C\I18N;

class DashboardPage extends AbstractPage
{
    protected function getTabs(): array
    {
        return [
            new DashboardTab(__('Dashboard', I18N::DOMAIN)),
        ];
    }
}
