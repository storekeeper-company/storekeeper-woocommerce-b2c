<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Frontend\Handlers\Seo\StorekeeperHandler;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class Seo
{
    const RANK_MATH_HANDLER = 'rank-math';
    const YOAST_HANDLER = 'yoast';
    const STOREKEEPER_HANDLER = 'storekeeper';
    const NO_HANDLER = 'none';

    public function prepareSeo($markdown, $product)
    {
        $selectedHandler = StoreKeeperOptions::get(StoreKeeperOptions::SEO_HANDLER, self::STOREKEEPER_HANDLER);

        if (self::STOREKEEPER_HANDLER === $selectedHandler) {
            $handler = new StorekeeperHandler();
            $handler->handle($markdown, $product);
        }
    }
}
