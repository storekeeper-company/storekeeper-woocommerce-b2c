<?php

namespace StoreKeeper\WooCommerce\B2C\Traits;

trait ConsoleProgressBarTrait
{
    /* @var \cli\progress\Bar|null $progressBar */
    protected $progressBar;

    public function createProgressBar(int $count, string $message): void
    {
        $progressBar = null;
        if (class_exists('WP_CLI') && class_exists('\cli\progress\Bar')) {
            $progressBar = new \cli\progress\Bar($message, $count);
        }
        $this->progressBar = $progressBar;

        if (!is_null($this->progressBar)) {
            $this->progressBar->display();
        }
    }

    public function tickProgressBar(): void
    {
        if (!is_null($this->progressBar)) {
            $this->progressBar->tick();
        }
    }

    public function endProgressBar(): void
    {
        if (!is_null($this->progressBar)) {
            $this->progressBar->finish();
        }
    }
}
