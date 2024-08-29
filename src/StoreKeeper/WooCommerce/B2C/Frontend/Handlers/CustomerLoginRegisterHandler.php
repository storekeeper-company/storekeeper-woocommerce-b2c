<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\Objects\ShopCustomer;

class CustomerLoginRegisterHandler implements WithHooksInterface
{
    public function registerHooks(): void
    {
        add_action('wp_login', [$this, 'loginBackendSync'], null, 2);
        add_action('user_register', [$this, 'registerBackendSync'], null, 2);
    }

    /**
     * @throws \Exception
     */
    public function loginBackendSync($user_login, $user)
    {
        if (!is_user_logged_in()) {
            return;
        }

        try {
            $customer = new ShopCustomer($user->ID);
            if (!$customer->is_customer_email_known()) {
                $customer->sync_customer_to_manage();
            }
        } catch (\Throwable $e) {
            LoggerFactory::create('shop_customer')->error(
                'Cannot synchronizeCustomer:  '.$e->getMessage(),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
        }
    }

    /**
     * @throws \Exception
     */
    public function registerBackendSync($user_id)
    {
        if (!is_user_logged_in()) {
            return;
        }

        try {
            $customer = new ShopCustomer($user_id);
            if (!$customer->is_customer_email_known()) {
                $customer->sync_customer_to_manage();
            }
        } catch (\Throwable $e) {
            LoggerFactory::create('shop_customer')->error(
                'Cannot synchronizeCustomer:  '.$e->getMessage(),
                [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
        }
    }
}
