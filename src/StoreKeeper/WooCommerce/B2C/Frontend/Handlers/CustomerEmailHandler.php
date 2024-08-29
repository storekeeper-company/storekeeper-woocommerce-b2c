<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\Tools\CustomerFinder;
use WooCommerce;

class CustomerEmailHandler implements WithHooksInterface
{
    public function registerHooks(): void
    {
        add_action('woocommerce_registration_errors', [$this, 'validateEmailOnRegistration'], 10, 3);
        add_action('woocommerce_checkout_process', [$this, 'validateEmailCheckout']);
    }

    public function validateEmailOnRegistration($validation_errors, $username, $email)
    {
        if (!empty($email) && !CustomerFinder::isValidEmail($email)) {
            // see: ./plugins/woocommerce/includes/wc-user-functions.php:wc_create_new_customer
            $validation_errors->add(
                'registration-error-invalid-email',
                __('Please provide a valid email address.', 'woocommerce')
            );
        }

        return $validation_errors;
    }

    public function validateEmailCheckout()
    {
        $email = $_POST['billing_email'] ?? ''; // Get the email address from the POST data

        if (!empty($email) && !CustomerFinder::isValidEmail($email)) {
            wc_add_notice(
                __('Please provide a valid email address.', 'woocommerce'),
                'error'
            );
        }
    }
}
