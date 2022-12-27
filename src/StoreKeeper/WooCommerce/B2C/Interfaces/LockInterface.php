<?php

namespace StoreKeeper\WooCommerce\B2C\Interfaces;

interface LockInterface
{
    public function lock(): bool;

    public function unlock();
}
