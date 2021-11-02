<?php

namespace StoreKeeper\WooCommerce\B2C\Factories;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class WpAdminHandler extends StreamHandler
{
    public function __construct(?int $level = Logger::DEBUG)
    {
        parent::__construct('php://output', $level, false);

        // print the messages as soon as they are available
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);
    }

    protected function write(array $record): void
    {
        parent::write($record);

        ob_flush();
        flush();
    }
}
