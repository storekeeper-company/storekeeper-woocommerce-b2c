<?php

namespace StoreKeeper\WooCommerce\B2C\Cron;

use Exception;
use StoreKeeper\WooCommerce\B2C\Commands\CommandRunner;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;

class ProcessTaskCron
{
    /**
     * @throws BaseException
     * @throws Exception
     */
    public function execute(): void
    {
        $commands = CommandRunner::getSubProcessInputString(
            ProcessAllTasks::getCommandName()
        );

        $runner = Core::getCommandRunner();

        $runner->setConsoleLogger();
        $exit = $runner->executeFromInputJson($commands);
        exit($exit);
    }
}
