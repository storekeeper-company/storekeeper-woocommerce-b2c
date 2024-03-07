<?php

namespace StoreKeeper\WooCommerce\B2C\Options;

class WooCommerceOptions extends AbstractOptions
{
    public const API_KEY = 'api-key';
    public const WOOCOMMERCE_TOKEN = 'woocommerce-token';
    public const WOOCOMMERCE_UUID = 'woocommerce-uuid';
    public const WOOCOMMERCE_INFO_TOKEN = 'woocommerce-info-token';

    public const LAST_SYNC_RUN = 'last-sync-run';
    public const SUCCESS_SYNC_RUN = 'success-sync-run';
    public const ORDER_PREFIX = 'order-prefix';

    public static function getApiKey(?string $siteUrl = null)
    {
        if (empty($siteUrl)) {
            $siteUrl = site_url();
        }
        $json = json_encode([
            'token' => self::get(self::WOOCOMMERCE_TOKEN), // Needs to the same over the applications lifespan.
            'webhook_url' => self::getWebhookUrl($siteUrl),
        ], JSON_THROW_ON_ERROR);

        return base64_encode($json);
    }

    public static function getWebhookUrl(?string $siteUrl = null): string
    {
        if (empty($siteUrl)) {
            $siteUrl = site_url();
        }

        return $siteUrl.'/?rest_route=/storekeeper-woocommerce-b2c/v1/webhook/'; // Endpoint
    }

    public static function resetToken($length = 64)
    {
        $token = self::createToken($length);
        WooCommerceOptions::set(WooCommerceOptions::WOOCOMMERCE_TOKEN, $token);
    }

    public static function createToken($length = 64)
    {
        $random_bytes = openssl_random_pseudo_bytes($length / 2);

        return bin2hex($random_bytes);
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
