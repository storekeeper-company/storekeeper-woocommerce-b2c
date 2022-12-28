<?php

namespace StoreKeeper\WooCommerce\B2C\Interfaces;

use StoreKeeper\WooCommerce\B2C\Exceptions\LockActiveException;
use StoreKeeper\WooCommerce\B2C\Exceptions\LockException;
use StoreKeeper\WooCommerce\B2C\Exceptions\LockTimeoutException;

interface LockInterface
{
    /**
     * @throws LockException|LockTimeoutException|LockActiveException
     */
    public function lock(): bool;

    public function unlock();
}
