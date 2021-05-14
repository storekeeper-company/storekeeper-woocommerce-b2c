<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

class SyncWoocommerceProducts extends AbstractSyncCommand
{
    /**
     * @return mixed|void
     *
     * @throws \Exception
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            // Try to get the total amount from the assoc arguments, if they are not there calculate the total amount.
            $total_amount = key_exists('total_amount', $assoc_arguments) ?
                $assoc_arguments['total_amount'] :
                $this->getAmountOfProductsInBackend();

            // Sync a page of products
            $this->runSubCommandWithPagination(
                SyncWoocommerceProductPage::getCommandName(),
                $total_amount,
                self::AMOUNT
            );
        }
    }
}
