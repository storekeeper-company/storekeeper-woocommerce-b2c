<?php

namespace StoreKeeper\WooCommerce\B2C\Exceptions;

class WpRestException extends BaseException
{
    public function getHttpCode(): int
    {
        $code = 500;
        if (!empty($this->code)) {
            $code = $this->code;
        }

        return $code;
    }
}
