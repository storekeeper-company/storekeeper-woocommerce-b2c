<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs\LocationsTab;
use StoreKeeper\WooCommerce\B2C\I18N;

class LocationsPage extends AbstractPage
{

    protected function getTabs(): array
    {
        return [
            new LocationsTab(__('Locations', I18N::DOMAIN), $this->getSlug())
        ];
    }
}
