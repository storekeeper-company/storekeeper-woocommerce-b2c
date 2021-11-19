<?php

namespace StoreKeeper\WooCommerce\B2C\Interfaces;

use cli\progress\Bar;

interface WithConsoleProgressBarInterface
{
    public function createProgressBar(int $count, string $message): Bar;

    public function tickProgressBar(): void;

    public function endProgressBar(): void;
}
