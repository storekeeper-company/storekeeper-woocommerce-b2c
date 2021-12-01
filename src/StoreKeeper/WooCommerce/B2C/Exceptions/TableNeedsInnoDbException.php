<?php

namespace StoreKeeper\WooCommerce\B2C\Exceptions;

use Throwable;

class TableNeedsInnoDbException extends BaseException
{
    protected $tableName;

    public function __construct(string $table, Throwable $previous = null)
    {
        parent::__construct("Table $table should be using InnoDB engine", 0, $previous);
        $this->tableName = $table;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }
}
