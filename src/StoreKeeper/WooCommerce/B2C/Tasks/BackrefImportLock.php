<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;

class BackrefImportLock extends MysqlLock
{
    const LOCK_PREFIX  = 'sk_bc_';

    public function __construct(string $backref, int $timeout = 15)
    {
        parent::__construct(self::LOCK_PREFIX.$backref, $timeout);
    }

}
