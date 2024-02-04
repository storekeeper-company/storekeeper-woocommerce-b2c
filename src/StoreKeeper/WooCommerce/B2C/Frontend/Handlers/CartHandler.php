<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;
use StoreKeeper\WooCommerce\B2C\Tools\CustomerFinder;
use WooCommerce;

class CartHandler implements WithHooksInterface
{
    public function registerHooks(): void
    {
        add_action('woocommerce_cart_calculate_fees', [$this, 'addEmballageFee'], 11);

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

    public function addEmballageFee()
    {
        /* @var WooCommerce $woocommerce */
        global $woocommerce;

        $items = $woocommerce->cart->get_cart();

        $lastEmballageTaxRateId = null;
        $totalEmballagePriceInCents = 0;
        foreach ($items as $values) {
            $product = wc_get_product($values['product_id']);
            $quantity = $values['quantity'];
            if ($product) {
                if ($product->meta_exists(ProductImport::PRODUCT_EMBALLAGE_PRICE_META_KEY)) {
                    $emballagePrice = $product->get_meta(ProductImport::PRODUCT_EMBALLAGE_PRICE_META_KEY);
                    $totalEmballagePriceInCents += round($emballagePrice * 100) * $quantity;
                }

                if ($product->meta_exists(ProductImport::PRODUCT_EMBALLAGE_TAX_ID_META_KEY)) {
                    $lastEmballageTaxRateId = $product->get_meta(ProductImport::PRODUCT_EMBALLAGE_TAX_ID_META_KEY);
                }
            }
        }

        if ($totalEmballagePriceInCents > 0) {
            $totalEmballagePrice = round($totalEmballagePriceInCents / 100, 2);
            $emballagePrice = [
                'name' => __('Emballage fee', I18N::DOMAIN),
                'amount' => $totalEmballagePrice,
                OrderExport::IS_EMBALLAGE_FEE_KEY => true,
            ];
            if ($lastEmballageTaxRateId) {
                $emballagePrice[OrderExport::TAX_RATE_ID_FEE_KEY] = $lastEmballageTaxRateId;
            }
            $woocommerce->cart->fees_api()->add_fee($emballagePrice);
        }
    }
}
