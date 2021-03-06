<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeOptionModel;
use StoreKeeper\WooCommerce\B2C\Models\PaymentModel;
use StoreKeeper\WooCommerce\B2C\Models\RefundModel;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;
use StoreKeeper\WooCommerce\B2C\Tools\RedirectHandler;

class Activator
{
    public function run()
    {
        $this->setWooCommerceToken();
        $this->setWooCommerceUuid();
        $this->setOrderPrefix();
        $this->setMainCategoryId();
        $this->ensureModelTables();
        $this->createRedirectTable();
        $this->setVersion();
    }

    private function setWooCommerceToken($length = 64)
    {
        if (!WooCommerceOptions::exists(WooCommerceOptions::WOOCOMMERCE_TOKEN)) {
            $random_bytes = openssl_random_pseudo_bytes($length / 2);
            $token = bin2hex($random_bytes);
            WooCommerceOptions::set(WooCommerceOptions::WOOCOMMERCE_TOKEN, $token);
        }
    }

    private function setWooCommerceUuid()
    {
        if (!WooCommerceOptions::exists(WooCommerceOptions::WOOCOMMERCE_UUID)) {
            $uuid = wp_generate_uuid4();
            WooCommerceOptions::set(WooCommerceOptions::WOOCOMMERCE_UUID, $uuid);
        }
    }

    private function createRedirectTable()
    {
        RedirectHandler::createTable(); // Create the table if it doesn't exist
    }

    private function setOrderPrefix()
    {
        if (!WooCommerceOptions::exists(WooCommerceOptions::ORDER_PREFIX)) {
            WooCommerceOptions::set(WooCommerceOptions::ORDER_PREFIX, 'WC');
        }
    }

    private function setMainCategoryId()
    {
        if (!StoreKeeperOptions::exists(StoreKeeperOptions::MAIN_CATEGORY_ID)) {
            StoreKeeperOptions::set(StoreKeeperOptions::MAIN_CATEGORY_ID, 0);
        }
    }

    private function setVersion()
    {
        StoreKeeperOptions::set(StoreKeeperOptions::INSTALLED_VERSION, STOREKEEPER_WOOCOMMERCE_B2C_VERSION);
    }

    protected function ensureModelTables(): void
    {
        WebhookLogModel::ensureTable();
        TaskModel::ensureTable();
        AttributeModel::ensureTable();
        AttributeOptionModel::ensureTable();
        PaymentModel::ensureTable();
        RefundModel::ensureTable();
    }
}
