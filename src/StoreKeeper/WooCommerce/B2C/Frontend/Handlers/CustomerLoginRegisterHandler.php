<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Objects\GOCustomer;

class CustomerLoginRegisterHandler
{
    const CONTEXT = 'edit';

    /**
     * @param $user_login
     * @param $user
     *
     * @throws \Exception
     */
    public function loginBackendSync($user_login, $user)
    {
        if (!is_user_logged_in()) {
            return;
        }

        $customer = new GOCustomer($user->ID);
        if (!$customer->is_customer_email_known()) {
            $customer->sync_customer_to_manage();
        }
    }

    /**
     * @param $user_id
     *
     * @throws \Exception
     */
    public function registerBackendSync($user_id)
    {
        if (!is_user_logged_in()) {
            return;
        }

        $customer = new GOCustomer($user_id);
        if (!$customer->is_customer_email_known()) {
            $customer->sync_customer_to_manage();
        }
    }
}
