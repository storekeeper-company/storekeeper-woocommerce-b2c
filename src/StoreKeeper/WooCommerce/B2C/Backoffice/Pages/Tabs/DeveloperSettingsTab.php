<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages\Tabs;

use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\AbstractTab;
use StoreKeeper\WooCommerce\B2C\Backoffice\Pages\FormElementTrait;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;

class DeveloperSettingsTab extends AbstractTab
{
    use FormElementTrait;

    protected function getStylePaths(): array
    {
        return [];
    }

    public function render(): void
    {
        $this->renderFormStart();

        $data = [
            WooCommerceOptions::WOOCOMMERCE_TOKEN => WooCommerceOptions::get(WooCommerceOptions::WOOCOMMERCE_TOKEN),
            WooCommerceOptions::WOOCOMMERCE_UUID => WooCommerceOptions::get(WooCommerceOptions::WOOCOMMERCE_UUID),
        ];

        if (StoreKeeperOptions::isConnected()) {
            $url_pattern = '/([http|https]*:\/\/).*-(.*)/';
            preg_match($url_pattern, StoreKeeperOptions::get(StoreKeeperOptions::API_URL), $matches);
            $data = array_merge(
                [
                    StoreKeeperOptions::API_URL => StoreKeeperOptions::get(StoreKeeperOptions::API_URL),
                    'possible backoffice-url' => "$matches[1]$matches[2]",
                    StoreKeeperOptions::SYNC_AUTH => json_encode(
                        StoreKeeperOptions::get(StoreKeeperOptions::SYNC_AUTH)
                    ),
                    StoreKeeperOptions::GUEST_AUTH => json_encode(
                        StoreKeeperOptions::get(StoreKeeperOptions::GUEST_AUTH)
                    ),
                ],
                $data
            );
        }

        foreach ($data as $name => $value) {
            $this->renderFormGroup($name, $value);
        }

        $this->renderFormEnd();
    }
}
