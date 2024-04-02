<?php

namespace StoreKeeper\WooCommerce\B2C\Exceptions;

use StoreKeeper\WooCommerce\B2C\I18N;

class WordpressException extends BaseException
{
    /**
     * WordpressException constructor.
     *
     * @param string $message
     * @param int    $code
     */
    public function __construct($message = '', $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct(__('Wordpress error message: ', I18N::DOMAIN).$message, $code, $previous);
    }
}
