<?php

namespace StoreKeeper\WooCommerce\B2C\Options;

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo;
use StoreKeeper\WooCommerce\B2C\I18N;

class StoreKeeperOptions extends AbstractOptions
{
    public const VENDOR = 'StoreKeeper';
    public const PLATFORM_NAME = 'WordPress';

    public const API_URL = 'api-url';
    public const OLD_API_URL = 'old-api-url';
    public const GUEST_AUTH = 'guest-auth';
    public const SYNC_AUTH = 'sync-auth';
    public const SYNC_PROFILE = 'sync-profile';
    public const MAIN_CATEGORY_ID = 'main-category-id';
    public const NOTIFY_ON_BACKORDER = 'notify-on-backorder';
    public const PAYMENT_GATEWAY_ACTIVATED = 'payment-gateway-activated';
    public const SHIPPING_METHOD_ACTIVATED = 'shipping-method-activated';
    public const CATEGORY_DESCRIPTION_HTML = 'category-description-html';
    public const SYNC_MODE = 'sync-mode';
    public const INSTALLED_VERSION = 'installed-version';

    public const SYNC_MODE_FULL_SYNC = 'sync-mode-full-sync';
    public const SYNC_MODE_ORDER_ONLY = 'sync-mode-order-only';
    public const SYNC_MODE_PRODUCT_ONLY = 'sync-mode-product-only';
    public const SYNC_MODE_NONE = 'sync-mode-none';

    public const SEO_HANDLER = 'seo-handler';

    public const IMAGE_CDN_PREFIX = 'image_cdn_prefix';

    public const MODES_WITH_CUSTOMERS_SYNC = [
        StoreKeeperOptions::SYNC_MODE_FULL_SYNC,
        StoreKeeperOptions::SYNC_MODE_ORDER_ONLY,
    ];

    public const MODES_WITH_ORDERS_SYNC = [
        StoreKeeperOptions::SYNC_MODE_FULL_SYNC,
        StoreKeeperOptions::SYNC_MODE_ORDER_ONLY,
    ];

    public const MODES_WITH_PAYMENTS = [
        StoreKeeperOptions::SYNC_MODE_FULL_SYNC,
        StoreKeeperOptions::SYNC_MODE_ORDER_ONLY,
    ];

    public const MODES_WITH_SHIPPING_METHODS = [
        StoreKeeperOptions::SYNC_MODE_FULL_SYNC,
        StoreKeeperOptions::SYNC_MODE_ORDER_ONLY,
    ];

    public const ORDER_SYNC_FROM_DATE = 'sync-order-from-date';

    public const BARCODE_MODE = 'barcode-mode';
    public const BARCODE_META_FALLBACK = 'storekeeper_barcode';

    // Frontend
    public const VALIDATE_CUSTOMER_ADDRESS = 'validate-customer-address';
    public const IMAGE_CDN = 'image-cdn';

    /**
     * returns true if the WooCommerce is connected to the StoreKeeper backend.
     */
    public static function disconnect()
    {
        self::delete(self::API_URL);
        self::delete(self::GUEST_AUTH);
        self::delete(self::SYNC_AUTH);

        return true;
    }

    /**
     * returns true if the WooCommerce is connected to the StoreKeeper backend.
     */
    public static function isConnected()
    {
        if (
            self::exists(self::API_URL)
            && self::exists(self::GUEST_AUTH)
            && self::exists(self::SYNC_AUTH)
        ) {
            return true;
        }

        return false;
    }

    public static function getSyncMode(): string
    {
        return self::get(self::SYNC_MODE, self::SYNC_MODE_ORDER_ONLY);
    }

    public static function getBackofficeUrl()
    {
        list($full, $schema, $api, $hostname) = self::getExplodedApiUrl();

        return $schema.$hostname;
    }

    public static function getBarcodeOptions()
    {
        $default = sprintf(
            __('Default (%s)', I18N::DOMAIN),
            StoreKeeperOptions::BARCODE_META_FALLBACK
        );
        $options = [];
        $options[StoreKeeperOptions::BARCODE_META_FALLBACK] = $default;

        $plugins = get_plugins();
        foreach ($plugins as $file => $data) {
            if (is_plugin_active($file)) {
                if ('woo-add-gtin/woocommerce-gtin.php' === $file) {
                    $options[sanitize_key($file)] = $data['Title'].' (hwp_var_gtin,hwp_product_gtin)';
                }
            }
        }

        return $options;
    }

    public static function getBarcodeMetaKey(\WC_Product $product)
    {
        $mode = self::getBarcodeMode();
        if (sanitize_key('woo-add-gtin/woocommerce-gtin.php') === $mode) {
            if ('variation' === $product->get_type()) {
                return 'hwp_var_gtin';
            }

            return 'hwp_product_gtin';
        }

        return self::BARCODE_META_FALLBACK;
    }

    public static function getBarcodeMode()
    {
        return self::get(self::BARCODE_MODE, self::BARCODE_META_FALLBACK);
    }

    public static function getSeoHandler()
    {
        return self::get(self::SEO_HANDLER, Seo::STOREKEEPER_HANDLER);
    }

    private static function getExplodedApiUrl(): array
    {
        $regex = '/([http|https]*:\/\/)(.*)-(.*)/';
        $apiUrl = self::get(self::API_URL);
        if (1 === preg_match($regex, $apiUrl, $matches)) {
            list($full, $schema, $api, $hostname) = $matches;

            return [$full, $schema, $api, $hostname];
        }

        return ['', '', '', ''];
    }

    public static function isOrderSyncEnabled(): bool
    {
        return in_array(self::getSyncMode(), self::MODES_WITH_ORDERS_SYNC, true);
    }

    public static function isCustomerSyncEnabled(): bool
    {
        return in_array(self::getSyncMode(), self::MODES_WITH_CUSTOMERS_SYNC, true);
    }

    public static function isPaymentSyncEnabled(): bool
    {
        return in_array(self::getSyncMode(), self::MODES_WITH_PAYMENTS, true);
    }

    public static function isPaymentGatewayActive(): bool
    {
        return 'yes' === self::get(self::PAYMENT_GATEWAY_ACTIVATED, 'yes')
            && self::isConnected();
    }

    public static function isShippingMethodAllowedForCurrentSyncMode(): bool
    {
        return in_array(self::getSyncMode(), self::MODES_WITH_SHIPPING_METHODS, true);
    }

    public static function isImageCdnEnabled(): bool
    {
        return 'yes' === self::get(self::IMAGE_CDN, 'yes');
    }

    public static function isShippingMethodSyncEnabled(): bool
    {
        return 'yes' === self::get(self::SHIPPING_METHOD_ACTIVATED, 'yes');
    }
}
