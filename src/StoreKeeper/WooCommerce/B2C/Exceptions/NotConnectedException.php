<?php

namespace StoreKeeper\WooCommerce\B2C\Exceptions;

class NotConnectedException extends BaseException
{
    /**
     * NotConnectedException constructor.
     */
    public function __construct($message = '', $code = 0, ?\Throwable $previous = null)
    {
        if (empty($message)) {
            $message = 'Plugin is not connected to backoffice';
        }
        parent::__construct($message, $code, $previous);
    }
}
