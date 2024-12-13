<?php

namespace StoreKeeper\WooCommerce\B2C\Blocks;

use Psr\Log\LoggerAwareInterface;

interface BlockTypeRegistrarInterface extends LoggerAwareInterface
{

    /**
     * Register StoreKeeper block type
     *
     * @return bool|\WP_Block_Type
     */
    public function register();
}
