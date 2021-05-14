<?php

namespace StoreKeeper\WooCommerce\B2C\Interfaces;

interface IFileExport
{
    /**
     * Returns the export file type.
     */
    public function getType(): string;

    public function runExport(?string $exportLanguage): string;
}
