<?php

namespace StoreKeeper\WooCommerce\B2C\Exceptions;

class LogDirectoryUnwritableException extends BaseException
{
    private string $logDirectory;

    public function __construct(string $logDirectory, $message = '', $code = 0, ?\Throwable $previous = null)
    {
        $this->logDirectory = $logDirectory;
        parent::__construct($message, $code, $previous);
    }

    public function getLogDirectory(): string
    {
        return $this->logDirectory;
    }
}
