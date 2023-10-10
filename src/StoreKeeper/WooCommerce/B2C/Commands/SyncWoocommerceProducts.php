<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\NoLoggerException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\I18N;

class SyncWoocommerceProducts extends AbstractSyncCommand
{
    public static function getShortDescription(): string
    {
        return __('Sync all products.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Sync all products from Storekeeper Backoffice and making sure that it is being executed by pages to avoid timeouts.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'assoc',
                'name' => 'total-amount',
                'description' => __('Specify total amount of products from Storekeeper Backoffice. By default, counts the total amount of products by checking the Storekeeper Backoffice', I18N::DOMAIN),
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
     * @return mixed|void
     *
     * @throws \Exception
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        $wpLogDirectory = LoggerFactory::getWpLogDirectory();

        if (is_null($wpLogDirectory) && !Core::isTest()) {
            throw new NoLoggerException();
        }

        if ($this->prepareExecute()) {
            // Try to get the total amount from the assoc arguments, if they are not there calculate the total amount.
            $total_amount = key_exists('total-amount', $assoc_arguments) ?
                $assoc_arguments['total-amount'] :
                $this->getAmountOfProductsInBackend();

            // Sync a page of products
            $this->runSubCommandWithPagination(
                SyncWoocommerceProductPage::getCommandName(),
                $total_amount,
                self::AMOUNT,
                true,
                false,
                true,
                true
            );
        }
    }
}
