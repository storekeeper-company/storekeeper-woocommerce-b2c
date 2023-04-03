<?php

namespace StoreKeeper\WooCommerce\B2C\Exceptions;

class SqlException extends BaseException
{
    protected $sql;

    public function __construct($message, string $sql)
    {
        $this->sql = $sql;
        parent::__construct($message);
    }

    public function getSql(): string
    {
        return $this->sql;
    }
}
