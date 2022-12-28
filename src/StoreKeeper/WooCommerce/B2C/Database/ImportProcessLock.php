<?php

namespace StoreKeeper\WooCommerce\B2C\Database;

use ReflectionException;

class ImportProcessLock extends MySqlLock
{
    public const LOCK_PREFIX = 'sk_importlock_';

    /**
     * @throws ReflectionException
     */
    public function __construct(string $processClass, int $timeout = 15)
    {
        $reflect = new \ReflectionClass($processClass);
        parent::__construct(self::LOCK_PREFIX.$reflect->getShortName(), $timeout);
    }
}
