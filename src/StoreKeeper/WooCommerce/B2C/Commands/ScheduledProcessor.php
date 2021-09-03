<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Options\CronOptions;
use Throwable;

class ScheduledProcessor extends ProcessAllTasks
{
    /**
     * {@inheritDoc}
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        CronOptions::set(CronOptions::LAST_PRE_EXECUTION_DATE, date('Y-m-d H:i:s'));
        $beforeCount = ProcessAllTasks::countTasks();
        try {
            parent::execute($arguments, $assoc_arguments);
            CronOptions::set(CronOptions::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_SUCCESS);
            CronOptions::delete(CronOptions::LAST_POST_EXECUTION_ERROR);
        } catch (Throwable $throwable) {
            CronOptions::set(CronOptions::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_FAILED);
            CronOptions::set(CronOptions::LAST_POST_EXECUTION_ERROR, $throwable->getMessage());
            throw $throwable;
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
