<?php

namespace StoreKeeper\WooCommerce\B2C\Loggers;

use Psr\Log\LoggerInterface;

class WpCLILogger implements LoggerInterface
{
    public function emergency($message, array $context = [])
    {
        \WP_CLI::error($message.': '.json_encode($context));
    }

    public function alert($message, array $context = [])
    {
        \WP_CLI::error($message.': '.json_encode($context));
    }

    public function critical($message, array $context = [])
    {
        \WP_CLI::error($message.': '.json_encode($context));
    }

    public function error($message, array $context = [])
    {
        \WP_CLI::error($message.': '.json_encode($context));
    }

    public function warning($message, array $context = [])
    {
        \WP_CLI::warning($message.': '.json_encode($context));
    }

    public function notice($message, array $context = [])
    {
        \WP_CLI::log($message.': '.json_encode($context));
    }

    public function info($message, array $context = [])
    {
        \WP_CLI::log($message.': '.json_encode($context));
    }

    public function debug($message, array $context = [])
    {
        \WP_CLI::debug($message.': '.json_encode($context));
    }

    public function log($level, $message, array $context = [])
    {
        \WP_CLI::log("[$level] ".$message.': '.json_encode($context));
    }
}
