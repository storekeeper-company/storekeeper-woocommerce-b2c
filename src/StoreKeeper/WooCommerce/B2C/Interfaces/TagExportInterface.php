<?php

namespace StoreKeeper\WooCommerce\B2C\Interfaces;

interface TagExportInterface
{
    public function setShouldSkipEmptyTag(bool $shouldSkipEmptyTag): void;
}
