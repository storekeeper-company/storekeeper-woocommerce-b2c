<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\TaskProcessor;

use StoreKeeper\WooCommerce\B2C\Commands\CommandRunner;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Endpoints\AbstractEndpoint;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpRestException;

class TaskProcessorEndpoint extends AbstractEndpoint
{
    const ROUTE = 'process-tasks';

    /**
     * @throws WpRestException
     */
    public function handle()
    {
        try {
            $commands = CommandRunner::getSubProcessInputString(
                ProcessAllTasks::getCommandName()
            );

            $runner = Core::getCommandRunner();

            $runner->setConsoleLogger();
            $runner->executeFromInputJson($commands);
        } catch (\Throwable $throwable) {
            $this->logger->error($throwable->getMessage());
            throw new WpRestException($throwable->getMessage(), 401, $throwable);
        }
    }
}
