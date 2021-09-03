<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\TaskProcessor;

use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Endpoints\AbstractEndpoint;
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
        CronOptions::set(CronOptions::LAST_PRE_EXECUTION_DATE, date('Y-m-d H:i:s'));
        $beforeCount = ProcessAllTasks::countTasks();
        try {
            $runner = Core::getCommandRunner();
            $runner->execute(ProcessAllTasks::getCommandName(), [], [
                'limit' => self::TASK_LIMIT,
            ]);
            CronOptions::set(CronOptions::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_SUCCESS);
            CronOptions::delete(CronOptions::LAST_POST_EXECUTION_ERROR);
        } catch (\Throwable $throwable) {
            CronOptions::set(CronOptions::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_FAILED);
            CronOptions::set(CronOptions::LAST_POST_EXECUTION_ERROR, $throwable->getMessage());
            $this->logger->error($throwable->getMessage());
            throw new WpRestException($throwable->getMessage(), 401, $throwable);
        } finally {
            $afterCount = ProcessAllTasks::countTasks();
            if ($beforeCount !== $afterCount && 0 !== $beforeCount) {
                CronOptions::set(CronOptions::LAST_EXECUTION_HAS_PROCESSED, 'true');
            } else {
                CronOptions::set(CronOptions::LAST_EXECUTION_HAS_PROCESSED, 'false');
            }
        }
    }
}
