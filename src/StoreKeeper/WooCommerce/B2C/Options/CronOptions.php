<?php

namespace StoreKeeper\WooCommerce\B2C\Options;

use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;

class CronOptions extends AbstractOptions
{
    const RUNNER = 'cron-runner';
    const LAST_EXECUTION_STATUS = 'cron-last-execution-status';
    const LAST_EXECUTION_RUNNER = 'cron-last-execution-runner';
    const LAST_PRE_EXECUTION_DATE = 'cron-last-pre-execution-date';
    const LAST_EXECUTION_HAS_PROCESSED = 'cron-last-has-processed';
    const LAST_POST_EXECUTION_STATUS = 'cron-last-post-execution-status';
    const LAST_POST_EXECUTION_ERROR = 'cron-last-post-execution-error';

    public static function resetLastExecutionData(): void
    {
        static::set(self::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_UNEXECUTED);
        static::set(self::LAST_EXECUTION_RUNNER, null);
        static::delete(self::LAST_PRE_EXECUTION_DATE);
        static::set(self::LAST_EXECUTION_HAS_PROCESSED, 'false');
        static::set(self::LAST_POST_EXECUTION_STATUS, null);
        static::delete(self::LAST_POST_EXECUTION_ERROR);
    }
}
