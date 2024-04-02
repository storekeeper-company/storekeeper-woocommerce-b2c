<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Exceptions\LockActiveException;
use StoreKeeper\WooCommerce\B2C\Exceptions\NotConnectedException;

abstract class AbstractSyncCommand extends AbstractCommand
{
    /**
     * @throws NotConnectedException|\Exception
     */
    protected function prepareExecute(): bool
    {
        try {
            $this->lock();
            $this->setupApi();

            return true;
        } catch (LockActiveException $exception) {
            $this->logger->notice('Cannot run. lock on.');

            return false;
        }
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
