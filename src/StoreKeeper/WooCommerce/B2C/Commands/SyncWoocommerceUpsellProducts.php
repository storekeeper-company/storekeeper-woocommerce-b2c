<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Helpers\ProductHelper;
use StoreKeeper\WooCommerce\B2C\I18N;

class SyncWoocommerceUpsellProducts extends AbstractSyncCommand
{
    // The amount of upsell products to sync per page
    public const AMOUNT_PER_PAGE = 50;

    public static function getShortDescription(): string
    {
        return __('Sync all cross-sell products.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Sync all cross-sell products from Storekeeper Backoffice and making sure that it is being executed by pages to avoid timeouts.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'assoc',
                'name' => 'total-amount',
                'description' => __('Specify total amount of cross-sell products from Storekeeper Backoffice. By default, counts the total amount of products by checking the Storekeeper Backoffice', I18N::DOMAIN),
                'optional' => true,
            ],
            [
                'type' => 'flag',
                'name' => WpCliCommandRunner::SINGLE_PROCESS,
                'description' => __('Flag to prevent spawning of child processes. Having this might cause timeouts during execution.', I18N::DOMAIN),
                'optional' => true,
            ],
        ];
    }

    /**
     * @throws \StoreKeeper\WooCommerce\B2C\Exceptions\NotConnectedException
     * @throws \StoreKeeper\WooCommerce\B2C\Exceptions\SubProcessException
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            // Try to get the total amount from the assoc arguments, if they are not there calculate the total amount.
            $total_amount = array_key_exists('total-amount', $assoc_arguments) ?
                $assoc_arguments['total-amount'] :
                ProductHelper::getAmountOfProductsInWooCommerce();

            // Sync a page of cross sell products
            $this->runSubCommandWithPagination(
                SyncWoocommerceUpsellProductPage::getCommandName(),
                $total_amount,
                self::AMOUNT_PER_PAGE,
                true,
                true,
                false,
                true
            );
        }
    }
}
