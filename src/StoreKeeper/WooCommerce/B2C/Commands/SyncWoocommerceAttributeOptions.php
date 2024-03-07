<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\I18N;

class SyncWoocommerceAttributeOptions extends AbstractSyncCommand
{
    // The amount of attribute options to sync per page
    public const AMOUNT_PER_PAGE = 50;

    public static function getShortDescription(): string
    {
        return __('Sync all product attribute options.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Sync all product attribute options from Storekeeper Backoffice and making sure that it is being executed by pages to avoid timeouts. Note that this should be executed when attributes are already synchronized.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'assoc',
                'name' => 'total-amount',
                'description' => __('Specify total amount of attribute options from Storekeeper Backoffice. By default, counts the total amount of attribute options by checking the Storekeeper Backoffice', I18N::DOMAIN),
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
            $total_amount = key_exists('total-amount', $assoc_arguments) ?
                $assoc_arguments['total-amount'] :
                $this->getAmountOfAttributeOptionsInBackend();

            // Sync a page of attribute options
            $this->runSubCommandWithPagination(
                SyncWoocommerceAttributeOptionPage::getCommandName(),
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
