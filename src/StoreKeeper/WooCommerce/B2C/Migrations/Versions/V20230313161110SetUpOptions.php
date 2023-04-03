<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations\Versions;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Migrations\AbstractMigration;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;

class V20230313161110SetUpOptions extends AbstractMigration
{
    public function up(DatabaseConnection $connection): ?string
    {
        $this->setWooCommerceToken();
        $this->setWooCommerceUuid();
        $this->setOrderPrefix();
        $this->setMainCategoryId();

        return null;
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
}
