<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\WithConsoleProgressBarInterface;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use StoreKeeper\WooCommerce\B2C\Traits\ConsoleProgressBarTrait;

class ShippingMethodImport extends AbstractImport implements WithConsoleProgressBarInterface
{
    use ConsoleProgressBarTrait;

    protected function processItem(Dot $dotObject, array $options = [])
    {
//        $shippingMethod = WordpressExceptionThrower::throwExceptionOnWpError(
//            (
//                [
//                    'taxonomy' => self::WOOCOMMERCE_PRODUCT_TAG_TAXONOMY,
//                    'hide_empty' => false,
//                    'number' => 1,
//                    'meta_key' => 'storekeeper_id',
//                    'meta_value' => $StoreKeeperId,
//                ]
//            )
//        );
        // TODO: Implement processItem() method.
    }

    protected function getImportEntityName(): string
    {
        return __('shipping methods', I18N::DOMAIN);
    }
}