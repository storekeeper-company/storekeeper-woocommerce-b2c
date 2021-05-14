<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\StatusTab;
use StoreKeeper\WooCommerce\B2C\I18N;

class StatusPage extends AbstractPage
{
    protected function getTabs(): array
    {
        return [
            new StatusTab(__('Status', I18N::DOMAIN)),
        ];
    }
}
