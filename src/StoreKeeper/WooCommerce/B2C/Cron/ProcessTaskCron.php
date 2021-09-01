<?php

namespace StoreKeeper\WooCommerce\B2C\Cron;

use Exception;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
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
        try {
            $runner = Core::getCommandRunner();
            $runner->execute(ProcessAllTasks::getCommandName(), [
                'task_limit' => self::TASK_LIMIT,
            ]);
            StoreKeeperOptions::set(StoreKeeperOptions::CRON_EXECUTION_LAST_STATUS, CronRegistrar::STATUS_SUCCESS);
        } catch (Throwable $throwable) {
            StoreKeeperOptions::set(StoreKeeperOptions::CRON_EXECUTION_LAST_STATUS, CronRegistrar::STATUS_FAILED);
            throw $throwable;
        }
    }
}
