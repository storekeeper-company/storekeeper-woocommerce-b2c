<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class Updator
{
    public function updateAction()
    {
        try {
            $this->update();
        } catch (\Throwable $e){
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
            $this->handleUpdate();
        }
    }

    private function handleUpdate()
    {
        $activator = new Activator();
        $activator->run();
    }
}
