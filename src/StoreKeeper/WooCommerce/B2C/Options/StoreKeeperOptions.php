<?php

namespace StoreKeeper\WooCommerce\B2C\Options;

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
    const SYNC_MODE = 'sync-mode';
    const INSTALLED_VERSION = 'installed-version';

    const SYNC_MODE_FULL_SYNC = 'sync-mode-full-sync';
    const SYNC_MODE_ORDER_ONLY = 'sync-mode-order-only';

    const CRON_ENABLED = 'cron-enabled';
    const CRON_MODE = 'cron-mode'; // wp-cron or custom plugin
    const CRON_CUSTOM_INTERVAL = 'cron-custom-interval'; // interval in seconds

    /**
     * returns true if the WooCommerce is connected to the StoreKeeper backend.
     */
    public static function disconnect()
    {
        FeaturedAttributeOptions::deleteAttributes();
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
        return self::get(self::SYNC_MODE, self::SYNC_MODE_FULL_SYNC);
    }

    public static function getBackofficeUrl()
    {
        list($full, $schema, $api, $hostname) = self::getExplodedApiUrl();

        return $schema.$hostname;
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
}
