<?php

namespace StoreKeeper\WooCommerce\B2C\Cron;

use StoreKeeper\WooCommerce\B2C\Commands\CommandRunner;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Core;

class ProcessTaskCron
{
    public const TASK_LIMIT = 100;

    /**
     * @throws \Exception|\Throwable
     */
    public function execute(): void
    {
        CommandRunner::withCronCheck(CronRegistrar::RUNNER_WPCRON, function () {
            $runner = Core::getCommandRunner();
            $runner->execute(ProcessAllTasks::getCommandName(), [], [
                'limit' => self::TASK_LIMIT,
            ]);
        }, function ($throwable) {
            throw $throwable;
        });
    }
}
