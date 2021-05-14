<?php

namespace StoreKeeper\WooCommerce\B2C\Factories;

use Monolog\Handler\AbstractProcessingHandler;

class WpCliHandler extends AbstractProcessingHandler
{
    protected function write(array $record): void
    {
        \WP_CLI::out((string) $record['formatted']);
    }
}
