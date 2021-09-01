<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\TaskProcessor;

use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Endpoints\AbstractEndpoint;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpRestException;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class TaskProcessorEndpoint extends AbstractEndpoint
{
    const ROUTE = 'process-tasks';
    const TASK_LIMIT = 100;

    /**
     * @throws WpRestException
     */
    public function handle()
    {
        try {
            $runner = Core::getCommandRunner();
            $runner->execute(ProcessAllTasks::getCommandName(), [
                'task_limit' => self::TASK_LIMIT,
            ]);
            StoreKeeperOptions::set(StoreKeeperOptions::CRON_EXECUTION_LAST_STATUS, CronRegistrar::STATUS_SUCCESS);
        } catch (\Throwable $throwable) {
            StoreKeeperOptions::set(StoreKeeperOptions::CRON_EXECUTION_LAST_STATUS, CronRegistrar::STATUS_FAILED);
            $this->logger->error($throwable->getMessage());
            throw new WpRestException($throwable->getMessage(), 401, $throwable);
        }
    }
}
