<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class Updator
{
    const ZERO_VERSION = '0.0.0';

    public function updateAction()
    {
        try {
            $this->update();
        } catch (\Throwable $e) {
            $txt = sprintf(
                __(
                    'Failed to update \'StoreKeeper for WooCommerce\' plugin to version %s',
                    I18N::DOMAIN
                ),
                STOREKEEPER_WOOCOMMERCE_B2C_VERSION
            );
            $txt = esc_html($txt);
            $trace = nl2br(esc_html((string) $e));
            echo <<<HTML
<div class="notice notice-error">
<p style="color: red;">$txt</p>
<p>$trace</p>
</div>
HTML;

            return false;
        }

        return true;
    }

    public function update(bool $forceUpdate = false)
    {
        $currentVersion = STOREKEEPER_WOOCOMMERCE_B2C_VERSION;
        $databaseVersion = StoreKeeperOptions::get(StoreKeeperOptions::INSTALLED_VERSION, '0.0.0');
        if ($forceUpdate || version_compare($currentVersion, $databaseVersion, '>')) {
            $this->handleUpdate($databaseVersion);
        }
    }

    private function handleUpdate(string $databaseVersion)
    {
        if ($this->isUpgrade($databaseVersion)) {
            // it's an upgrade
            if (version_compare($databaseVersion, '7.2.1', '<')) {
                // default mode was added
                $isSet = StoreKeeperOptions::get(StoreKeeperOptions::SYNC_MODE, false);
                if (!$isSet) {
                    // previously it was full sync
                    StoreKeeperOptions::set(StoreKeeperOptions::SYNC_MODE, StoreKeeperOptions::SYNC_MODE_FULL_SYNC);
                }
            }
        }

        $activator = new Activator();
        $activator->run();
    }

    private function isUpgrade(string $databaseVersion): bool
    {
        return self::ZERO_VERSION !== $databaseVersion;
    }
}
