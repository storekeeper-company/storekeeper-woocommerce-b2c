<?php

namespace StoreKeeper\WooCommerce\B2C\Database;

class ImportProcessLock extends MySqlLock
{
    public const LOCK_PREFIX = 'sk_importlock_';

    public function __construct(string $process, int $timeout = 15)
    {
        parent::__construct(self::LOCK_PREFIX.$process, $timeout);
    }
}
