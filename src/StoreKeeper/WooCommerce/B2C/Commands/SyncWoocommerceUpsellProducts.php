<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Helpers\ProductHelper;

class SyncWoocommerceUpsellProducts extends AbstractSyncCommand
{
    // The amount of upsell products to sync per page
    const AMOUNT_PER_PAGE = 50;

    /**
     * Execute this command to sync the upsell products.
     *
     * @throws \StoreKeeper\WooCommerce\B2C\Exceptions\NotConnectedException
     * @throws \StoreKeeper\WooCommerce\B2C\Exceptions\SubProcessException
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            // Try to get the total amount from the assoc arguments, if they are not there calculate the total amount.
            $total_amount = key_exists('total_amount', $assoc_arguments) ?
                $assoc_arguments['total_amount'] :
                ProductHelper::getAmountOfProductsInWooCommerce();

            // Sync a page of cross sell products
            $this->runSubCommandWithPagination(
                SyncWoocommerceUpsellProductPage::getCommandName(),
                $total_amount,
                self::AMOUNT_PER_PAGE
            );
        }
    }
}
