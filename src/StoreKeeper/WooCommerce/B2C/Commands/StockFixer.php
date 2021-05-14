<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

class StockFixer extends AbstractCommand
{
    public function execute(array $arguments, array $assoc_arguments)
    {
        $cmd = 'php '.STOREKEEPER_WOOCOMMERCE_B2C_ABSPATH.'/scripts/stock-fix.php';

        echo 'Running: Stock fix...'.PHP_EOL;
        $process = new \Symfony\Component\Process\Process($cmd);
        $process->setTimeout(null);
        $process->start();
        $iterator = $process->getIterator($process::ITER_SKIP_ERR | $process::ITER_KEEP_OUTPUT);
        foreach ($iterator as $data) {
            echo $data;
        }
    }
}
