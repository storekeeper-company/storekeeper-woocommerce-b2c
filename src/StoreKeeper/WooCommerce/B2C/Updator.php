<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Backoffice\MenuStructure;
use StoreKeeper\WooCommerce\B2C\Backoffice\Notices\AdminNotices;
use StoreKeeper\WooCommerce\B2C\Exceptions\TableNeedsInnoDbException;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\Migrations\MigrationManager;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class Updator implements WithHooksInterface
{
    public function registerHooks(): void
    {
        add_action('load-plugins.php', [$this, 'updateAction']);
        add_action('load-plugin-install.php', [$this, 'updateAction']);
        add_action('upgrader_process_complete', [$this, 'onProcessComplete'], 0, 2);

        list($pages) = MenuStructure::getPages();
        foreach ($pages as $page) {
            add_action($page->getActionName(), [$this, 'updateAction']);
        }
    }

    public function updateAction()
    {
        try {
            $this->update();
        } catch (TableNeedsInnoDbException $e) {
            AdminNotices::showError(
                $this->getUpgradeFailMessage(),
                sprintf(
                    __(
                        'Table "%s" need to be using InnoDB ENGINE. <a href="%s">Go to status page</a> to fix this dependency.',
                        I18N::DOMAIN
                    ),
                    $e->getTableName(),
                    admin_url().'admin.php?page=storekeeper-status#table-innodb'
                )
            );

            return false;
        } catch (\Throwable $e) {
            AdminNotices::showException(
                $e,
                $this->getUpgradeFailMessage()
            );

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

    public function update()
    {
        $currentVersion = STOREKEEPER_WOOCOMMERCE_B2C_VERSION;
        $databaseVersion = StoreKeeperOptions::get(StoreKeeperOptions::INSTALLED_VERSION, '0.0.0');
        if (version_compare($currentVersion, $databaseVersion, '>')) {
            $activator = new Activator();
            $activator->run();
        } else {
            $manager = new MigrationManager();
            $manager->migrateAll();
        }
    }

    protected function getUpgradeFailMessage(): string
    {
        return sprintf(
            __(
                'Failed to update \'StoreKeeper for WooCommerce\' plugin to version %s.',
                I18N::DOMAIN
            ),
            STOREKEEPER_WOOCOMMERCE_B2C_VERSION
        );
    }
}
