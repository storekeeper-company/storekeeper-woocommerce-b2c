<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpCliException;

class ScheduledProcessor extends ProcessAllTasks
{
    /**
     * {@inheritDoc}
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        CommandRunner::withCronCheck(CronRegistrar::RUNNER_CRONTAB_CLI, function () use ($arguments, $assoc_arguments) {
            parent::execute($arguments, $assoc_arguments);
        }, function ($throwable) {
            throw $throwable;
        }, function ($invalidRunnerException) {
            throw new WpCliException("Error: {$invalidRunnerException->getMessage()}");
        });
    }
}
