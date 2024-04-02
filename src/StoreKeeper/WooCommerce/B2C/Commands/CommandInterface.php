<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use Psr\Log\LoggerAwareInterface;

interface CommandInterface extends LoggerAwareInterface
{
    public function execute(array $arguments, array $assoc_arguments);

    public static function getCommandName(): string;

    public static function getShortDescription(): string;

    public static function getLongDescription(): string;

    /**
     * Synopsis can be an array or string.
     *
     * @see https://make.wordpress.org/cli/handbook/guides/commands-cookbook/#wp_cliadd_commands-third-args-parameter
     *
     * @return array|string
     */
    public static function getSynopsis();
}
