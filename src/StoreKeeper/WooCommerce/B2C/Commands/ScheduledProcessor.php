<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpCliException;
use StoreKeeper\WooCommerce\B2C\I18N;

class ScheduledProcessor extends ProcessAllTasks
{
    public static function getLongDescription(): string
    {
        return parent::getLongDescription().__(' This is used for cron and has cron checks so you should execute wp sk process-all-tasks instead.', I18N::DOMAIN);
    }

    protected function getLockClass(): string
    {
        return ProcessAllTasks::class;
    }

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
