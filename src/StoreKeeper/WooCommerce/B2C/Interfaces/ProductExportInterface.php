<?php

namespace StoreKeeper\WooCommerce\B2C\Interfaces;

interface ProductExportInterface
{
    public function setShouldExportActiveProductsOnly(bool $shouldExportActiveProductsOnly): void;
}
