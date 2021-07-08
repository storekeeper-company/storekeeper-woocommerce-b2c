<?php

namespace StoreKeeper\WooCommerce\B2C\Options;

class WooCommerceOptions extends AbstractOptions
{
    const API_KEY = 'api-key';
    const WOOCOMMERCE_TOKEN = 'woocommerce-token';
    const WOOCOMMERCE_UUID = 'woocommerce-uuid';

    const LAST_SYNC_RUN = 'last-sync-run';
    const SUCCESS_SYNC_RUN = 'success-sync-run';
    const ORDER_PREFIX = 'order-prefix';

    public static function getApiKey(string $siteUrl = null)
    {
        if (empty($siteUrl)) {
            $siteUrl = site_url();
        }
        $json = json_encode(
            [
                'token' => self::get(self::WOOCOMMERCE_TOKEN), // Needs to the same over the applications lifespan.
                'webhook_url' => $siteUrl.'/?rest_route=/storekeeper-woocommerce-b2c/v1/webhook/', // Endpoint
            ]
        );

        return base64_encode($json);
    }

    public static function resetToken($length = 64)
    {
        $random_bytes = openssl_random_pseudo_bytes($length / 2);
        $token = bin2hex($random_bytes);
        WooCommerceOptions::set(WooCommerceOptions::WOOCOMMERCE_TOKEN, $token);
    }

    public static function getWooCommerceTypeFromProductType($shop_product_type)
    {
        switch ($shop_product_type) {
            case 'simple':
                return 'simple';
            case 'configurable':
                return 'variable';
            case 'configurable_assigned':
                return 'variation';
            default:
                return null;
        }
    }
}
