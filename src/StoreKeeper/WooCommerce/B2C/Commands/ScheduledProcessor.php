<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Exceptions\InvalidRunnerException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpCliException;
use StoreKeeper\WooCommerce\B2C\Options\CronOptions;
use Throwable;

class ScheduledProcessor extends ProcessAllTasks
{
    /**
     * {@inheritDoc}
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        $beforeCount = ProcessAllTasks::countNewTasks();
        try {
            CronRegistrar::validateRunner(CronRegistrar::RUNNER_CRONTAB_CLI);
            CronOptions::set(CronOptions::LAST_PRE_EXECUTION_DATE, date(DATE_RFC2822));
            parent::execute($arguments, $assoc_arguments);
            CronOptions::updateSuccessfulExecution();
        } catch (InvalidRunnerException $invalidRunnerException) {
            $beforeCount = 0;
            throw new WpCliException("Error: {$invalidRunnerException->getMessage()}");
        } catch (Throwable $throwable) {
            CronOptions::updateFailedExecution($throwable);
            throw $throwable;
        } finally {
            CronOptions::updateHasProcessed($beforeCount);
        }
    }
}
