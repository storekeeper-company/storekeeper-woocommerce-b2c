<?php

namespace StoreKeeper\WooCommerce\B2C\Exceptions;

class ConnectionTimedOutException extends BaseException
{
    protected int $taskId;

    public function __construct(int $taskId, $message = '', $code = 0, ?\Throwable $previous = null)
    {
        $this->taskId = $taskId;
        parent::__construct($message, $code, $previous);
    }

    public function getTaskId(): int
    {
        return $this->taskId;
    }
}
