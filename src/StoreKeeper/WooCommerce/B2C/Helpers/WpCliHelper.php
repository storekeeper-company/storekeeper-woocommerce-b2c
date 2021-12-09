<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers;

use StoreKeeper\WooCommerce\B2C\Core;

class WpCliHelper
{
    public static function attemptLineOutput(string $message): void
    {
        if (self::shouldPrint()) {
            \WP_CLI::line($message);
        }
    }

    public static function attemptSuccessOutput(string $message): void
    {
        if (self::shouldPrint()) {
            \WP_CLI::success($message);
        }
    }

    public static function setYellowOutputColor(string $message): string
    {
        if (self::shouldPrint()) {
            return \WP_CLI::colorize('%y'.$message.'%n');
        }

        return $message;
    }

    public static function setGreenOutputColor(string $message): string
    {
        if (self::shouldPrint()) {
            return \WP_CLI::colorize('%G'.$message.'%n');
        }

        return $message;
    }

    public static function shouldPrint(): bool
    {
        return !Core::isTest() && class_exists('WP_CLI');
    }
}
