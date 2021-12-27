<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo\StorekeeperHandler;
use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo\YoastHandler;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class Seo
{
    const YOAST_HANDLER = 'yoast';
    const STOREKEEPER_HANDLER = 'storekeeper';
    const NO_HANDLER = 'none';

    public function prepareSeo($markdown, $product)
    {
        $selectedHandler = StoreKeeperOptions::get(StoreKeeperOptions::SEO_HANDLER, self::STOREKEEPER_HANDLER);
        $handler = null;

        switch ($selectedHandler) {
            case self::STOREKEEPER_HANDLER:
                $handler = new StorekeeperHandler();
                break;
            case self::YOAST_HANDLER:
                $handler = new YoastHandler();
                break;
        }

        if (!is_null($handler)) {
            $handler->handle($markdown, $product);
        }
    }

    public static function isYoastActive(): bool
    {
        $activePlugins = apply_filters('active_plugins', get_option('active_plugins'));

        foreach ($activePlugins as $plugin) {
            if (strpos($plugin, 'wp-seo')) {
                return true;
            }
        }

        return false;
    }
}
