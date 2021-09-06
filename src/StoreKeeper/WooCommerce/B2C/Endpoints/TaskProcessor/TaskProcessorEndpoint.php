<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\TaskProcessor;

use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Endpoints\AbstractEndpoint;
use StoreKeeper\WooCommerce\B2C\Exceptions\InvalidRunnerException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpRestException;
use StoreKeeper\WooCommerce\B2C\Options\CronOptions;

class TaskProcessorEndpoint extends AbstractEndpoint
{
    const ROUTE = 'process-tasks';
    const TASK_LIMIT = 100;

    /**
     * @throws WpRestException
     */
    public function handle()
    {
        $beforeCount = ProcessAllTasks::countNewTasks();
        try {
            CronRegistrar::validateRunner(CronRegistrar::RUNNER_CRONTAB_API);
            CronOptions::set(CronOptions::LAST_PRE_EXECUTION_DATE, date(DATE_RFC2822));
            $runner = Core::getCommandRunner();
            $runner->execute(ProcessAllTasks::getCommandName(), [], [
                'limit' => self::TASK_LIMIT,
            ]);
            CronOptions::updateSuccessfulExecution();
        } catch (InvalidRunnerException $invalidRunnerException) {
            $beforeCount = 0;

            return [
                'error' => $invalidRunnerException->getMessage(),
            ];
        } catch (\Throwable $throwable) {
            CronOptions::updateFailedExecution($throwable);
            $this->logger->error($throwable->getMessage());
            throw new WpRestException($throwable->getMessage(), 401, $throwable);
        } finally {
            CronOptions::updateHasProcessed($beforeCount);
        }
    }
}
