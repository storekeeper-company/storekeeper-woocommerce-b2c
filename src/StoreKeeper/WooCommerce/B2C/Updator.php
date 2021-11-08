<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeModel\MigrateFromOldData;
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

    public function onProcessComplete(\WP_Upgrader $upgrader_object, $options)
    {
        if (isset($options['plugins']) && is_array($options['plugins'])) {
            foreach ($options['plugins'] as $each_plugin) {
                if (STOREKEEPER_WOOCOMMERCE_FILE === $each_plugin) {
                    $this->update();
                }
            }
        } elseif (isset($options['plugins']) && STOREKEEPER_WOOCOMMERCE_FILE === $options['plugins']) {
            $this->update();
        } elseif (isset($options['plugin']) && ('' != $options['plugin'])) {
            if (STOREKEEPER_WOOCOMMERCE_FILE === $options['plugin']) {
                $this->update();
            }
        }
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
            $this->upgradeDatabase($databaseVersion);
        }

        $activator = new Activator();
        $activator->run();
    }

    private function isUpgrade(string $databaseVersion): bool
    {
        return self::ZERO_VERSION !== $databaseVersion;
    }

    private function upgradeDatabase(string $databaseVersion): void
    {
        if (version_compare($databaseVersion, '7.2.1', '<')) {
            // default mode was added
            $isSet = StoreKeeperOptions::get(StoreKeeperOptions::SYNC_MODE, false);
            if (!$isSet) {
                // previously it was full sync
                StoreKeeperOptions::set(StoreKeeperOptions::SYNC_MODE, StoreKeeperOptions::SYNC_MODE_FULL_SYNC);
            }
        }
        if (version_compare($databaseVersion, '7.4.0', '<')) {
            AttributeModel::ensureTable();

            if (0 === AttributeModel::count()) {
                // no rows, lets copy
                $migrate = new MigrateFromOldData();
                $migrate->run();
            }
        }
    }
}
