<?php

namespace StoreKeeper\WooCommerce\B2C\Traits;

use StoreKeeper\WooCommerce\B2C\Core;

trait ConsoleProgressBarTrait
{
    /* @var \cli\progress\Bar|null $progressBar */
    protected $progressBar;

    public function createProgressBar(int $count, string $message): void
    {
        if (!Core::isTest()) {
            $progressBar = null;
            if (class_exists('WP_CLI') && class_exists('\cli\progress\Bar')) {
                $progressBar = new \cli\progress\Bar($message, $count);
            }
            $this->progressBar = $progressBar;

            if (!is_null($this->progressBar)) {
                $this->progressBar->display();
            }
        }
    }

    public function tickProgressBar(): void
    {
        if (!Core::isTest() && !is_null($this->progressBar)) {
            $this->progressBar->tick();
        }
    }

    public function endProgressBar(): void
    {
        if (!Core::isTest() && !is_null($this->progressBar)) {
            $this->progressBar->finish();
        }
    }
}
