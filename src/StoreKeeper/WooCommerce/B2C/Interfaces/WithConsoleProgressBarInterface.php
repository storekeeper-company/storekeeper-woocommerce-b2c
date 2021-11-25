<?php

namespace StoreKeeper\WooCommerce\B2C\Interfaces;

interface WithConsoleProgressBarInterface
{
    public function createProgressBar(int $count, string $message): void;

    public function tickProgressBar(): void;

    public function endProgressBar(): void;
}
