<?php

include_once __DIR__.'/../autoload.php';

\StoreKeeper\WooCommerce\B2C\Commands\CommandRunner::exitIFNotCli();

$command_contents = \StoreKeeper\WooCommerce\B2C\Commands\CommandRunner::getSubProcessInputString(
    \StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks::getCommandName()
);

include_once __DIR__.'/run-command-from-input.php';
