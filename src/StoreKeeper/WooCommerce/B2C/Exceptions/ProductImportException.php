<?php

namespace StoreKeeper\WooCommerce\B2C\Exceptions;

class ProductImportException extends BaseException
{
    private int $shopProductId;

    public function __construct(int $productId, $message = '', $code = 0, ?\Throwable $previous = null)
    {
        $this->shopProductId = $productId;
        parent::__construct($message, $code, $previous);
    }

    public function getShopProductId(): int
    {
        return $this->shopProductId;
    }
}
