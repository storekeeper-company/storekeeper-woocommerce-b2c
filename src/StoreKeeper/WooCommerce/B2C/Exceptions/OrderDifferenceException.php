<?php

namespace StoreKeeper\WooCommerce\B2C\Exceptions;

class OrderDifferenceException extends BaseException
{
    private array $shopExtras = [];
    private array $backofficeExtras = [];

    public function __construct(
        string $message = '',
        array $shopExtras = [],
        array $backofficeExtras = [],
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->setShopExtras($shopExtras);
        $this->setBackofficeExtras($backofficeExtras);
        parent::__construct($message, $code, $previous);
    }

    public function getShopExtras(): array
    {
        return $this->shopExtras;
    }

    public function setShopExtras(array $shopExtras): void
    {
        $this->shopExtras = $shopExtras;
    }

    public function getBackofficeExtras(): array
    {
        return $this->backofficeExtras;
    }

    public function setBackofficeExtras(array $backofficeExtras): void
    {
        $this->backofficeExtras = $backofficeExtras;
    }
}
