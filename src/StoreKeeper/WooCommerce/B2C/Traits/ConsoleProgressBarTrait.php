<?php

namespace StoreKeeper\WooCommerce\B2C\Traits;

use cli\progress\Bar;

trait ConsoleProgressBarTrait
{
    /* @var Bar $progressBar */
    protected $progressBar;

    public function createProgressBar(int $count, string $message): Bar
    {
        $progressBar = new Bar($message, $count);
        $this->progressBar = $progressBar;
        $this->progressBar->display();

        return $this->progressBar;
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
