<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

abstract class AbstractSyncCommand extends AbstractCommand
{
    public static function needsFullWpToExecute(): bool
    {
        return true;
    }

    /**
     * @throws \StoreKeeper\WooCommerce\B2C\Exceptions\NotConnectedException
     */
    protected function prepareExecute()
    {
        if (!$this->lock()) {
            $this->logger->notice('Cannot run. lock on.');

            return false;
        }
        $this->setupApi();

        return true;
    }

    /**
     * Fetch the total amount of products from the backend.
     *
     * @return int
     */
    protected function getAmountOfProductsInBackend()
    {
        $response = $this->api->getModule('ShopModule')->naturalSearchShopFlatProductForHooks(0, 0, 0, 1, null, null);
        $total = (int) $response['total'];

        return $total;
    }

    /**
     * Fetch the total amount of attribute options from the backend.
     *
     * @return int
     */
    protected function getAmountOfAttributeOptionsInBackend()
    {
        $response = $this->api->getModule('BlogModule')->listTranslatedAttributeOptions(0, 0, 1);
        $total = (int) $response['total'];

        return $total;
    }
}
