<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

class SyncWoocommerceAttributeOptions extends AbstractSyncCommand
{
    // The amount of attribute options to sync per page
    const AMOUNT_PER_PAGE = 50;

    /**
     * Sync all product attribute options (should be done after attributes are there).
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
                $this->getAmountOfAttributeOptionsInBackend();

            // Sync a page of attribute options
            $this->runSubCommandWithPagination(
                SyncWoocommerceAttributeOptionPage::getCommandName(),
                $total_amount,
                self::AMOUNT
            );
        }
    }
}
