<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

class WpCliHelper
{
    public static function attemptLineOutput(string $message): void
    {
        if (class_exists('WP_CLI')) {
            \WP_CLI::line($message);
        }
    }

    public static function attemptSuccessOutput(string $message): void
    {
        if (class_exists('WP_CLI')) {
            \WP_CLI::success($message);
        }
    }

    public static function setYellowOutputColor(string $message): string
    {
        if (class_exists('WP_CLI')) {
            return \WP_CLI::colorize('%y'.$message.'%n');
        }

        return $message;
    }

    public static function setGreenOutputColor(string $message): string
    {
        if (class_exists('WP_CLI')) {
            return \WP_CLI::colorize('%G'.$message.'%n');
        }

        return $message;
    }
}
