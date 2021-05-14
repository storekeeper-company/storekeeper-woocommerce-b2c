<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use Psr\Log\LoggerAwareInterface;

interface CommandInterface extends LoggerAwareInterface
{
    /**
     * @return mixed
     */
    public function execute(array $arguments, array $assoc_arguments);

    public static function getCommandName(): string;

    /**
     * if full wp is needed to execute the task.
     */
    public static function needsFullWpToExecute(): bool;
}
