<?php

namespace StoreKeeper\WooCommerce\B2C\Exceptions;

use StoreKeeper\WooCommerce\B2C\I18N;

class TableOperationSqlException extends SqlException
{
    protected $table;
    protected $action;

    public function __construct($message, string $table, string $action, string $sql = '')
    {
        $this->action = $action;
        $this->table = $table;
        parent::__construct(
            sprintf(
                __('Sql on %s::%s operation: %s', I18N::DOMAIN),
                $table,
                $this->action,
                $message
            ),
            $sql
        );
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}
