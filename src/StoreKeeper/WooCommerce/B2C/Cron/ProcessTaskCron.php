<?php

namespace StoreKeeper\WooCommerce\B2C\Cron;

use Exception;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Exceptions\InvalidRunnerException;
use StoreKeeper\WooCommerce\B2C\Options\CronOptions;
use Throwable;

class ProcessTaskCron
{
    public const TASK_LIMIT = 100;

    /**
     * @throws BaseException
     * @throws Exception|Throwable
     */
    public function execute(): void
    {
        $beforeCount = ProcessAllTasks::countNewTasks();
        try {
            CronRegistrar::validateRunner(CronRegistrar::RUNNER_WPCRON);
            CronOptions::set(CronOptions::LAST_PRE_EXECUTION_DATE, date(DATE_RFC2822));
            $runner = Core::getCommandRunner();
            $runner->execute(ProcessAllTasks::getCommandName(), [], [
                'limit' => self::TASK_LIMIT,
            ]);
            CronOptions::updateSuccessfulExecution();
        } catch (InvalidRunnerException $invalidRunnerException) {
            $beforeCount = 0;
            throw $invalidRunnerException;
        } catch (Throwable $throwable) {
            CronOptions::updateFailedExecution($throwable);
            throw $throwable;
        } finally {
            CronOptions::updateHasProcessed($beforeCount);
        }
    }
}
