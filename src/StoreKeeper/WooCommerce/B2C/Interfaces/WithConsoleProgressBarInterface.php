<?php

namespace StoreKeeper\WooCommerce\B2C\Interfaces;

interface WithConsoleProgressBarInterface
{
    /**
     * @return \cli\progress\Bar|null
     */
    public function createProgressBar(int $count, string $message);

    public function tickProgressBar(): void;

    public function endProgressBar(): void;
}
