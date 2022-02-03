<?php

namespace StoreKeeper\WooCommerce\B2C\Options;

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo;
use StoreKeeper\WooCommerce\B2C\I18N;

class StoreKeeperOptions extends AbstractOptions
{
    const API_URL = 'api-url';
    const OLD_API_URL = 'old-api-url';
    const GUEST_AUTH = 'guest-auth';
    const SYNC_AUTH = 'sync-auth';
    const SYNC_PROFILE = 'sync-profile';
    const MAIN_CATEGORY_ID = 'main-category-id';
    const NOTIFY_ON_BACKORDER = 'notify-on-backorder';
    const PAYMENT_GATEWAY_ACTIVATED = 'payment-gateway-activated';
    const CATEGORY_DESCRIPTION_HTML = 'category-description-html';
    const SYNC_MODE = 'sync-mode';
    const INSTALLED_VERSION = 'installed-version';

    const SYNC_MODE_FULL_SYNC = 'sync-mode-full-sync';
    const SYNC_MODE_ORDER_ONLY = 'sync-mode-order-only';
    const SYNC_MODE_PRODUCT_ONLY = 'sync-mode-product-only';
    const SYNC_MODE_NONE = 'sync-mode-none';

    const SEO_HANDLER = 'seo-handler';

    const MODES_WITH_CUSTOMERS_SYNC = [
        StoreKeeperOptions::SYNC_MODE_FULL_SYNC,
        StoreKeeperOptions::SYNC_MODE_ORDER_ONLY,
    ];

    const MODES_WITH_ORDERS_SYNC = [
        StoreKeeperOptions::SYNC_MODE_FULL_SYNC,
        StoreKeeperOptions::SYNC_MODE_ORDER_ONLY,
    ];

    const MODES_WITH_PAYMENTS = [
        StoreKeeperOptions::SYNC_MODE_FULL_SYNC,
        StoreKeeperOptions::SYNC_MODE_ORDER_ONLY,
    ];

    const ORDER_SYNC_FROM_DATE = 'sync-order-from-date';

    const BARCODE_MODE = 'barcode-mode';
    const BARCODE_META_FALLBACK = 'storekeeper_barcode';

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
            self::exists(self::API_URL) &&
            self::exists(self::GUEST_AUTH) &&
            self::exists(self::SYNC_AUTH)
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

    public static function getExplorerUrl()
    {
        list($full, $schema, $api, $hostname) = self::getExplodedApiUrl();

        return $schema.'explorer-'.$hostname;
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
}
