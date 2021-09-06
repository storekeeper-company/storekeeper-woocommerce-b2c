<?php

namespace StoreKeeper\WooCommerce\B2C\Options;

use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\I18N;

class CronOptions extends AbstractOptions
{
    const RUNNER = 'cron-runner';
    const LAST_EXECUTION_STATUS = 'cron-last-execution-status';
    const LAST_EXECUTION_RUNNER = 'cron-last-execution-runner';
    const LAST_PRE_EXECUTION_DATE = 'cron-last-pre-execution-date';
    const LAST_EXECUTION_HAS_PROCESSED = 'cron-last-has-processed';
    const LAST_POST_EXECUTION_STATUS = 'cron-last-post-execution-status';
    const LAST_POST_EXECUTION_ERROR = 'cron-last-post-execution-error';

    const INVALID_PREFIX = 'cron-invalid-run-';
    const WPCRON_INVALID_RUN_TIMESTAMP = self::INVALID_PREFIX.CronRegistrar::RUNNER_WPCRON;
    const CLI_INVALID_RUN_TIMESTAMP = self::INVALID_PREFIX.CronRegistrar::RUNNER_CRONTAB_CLI;
    const API_INVALID_RUN_TIMESTAMP = self::INVALID_PREFIX.CronRegistrar::RUNNER_CRONTAB_API;
    const INVALID_TIMESTAMPS = [
        self::WPCRON_INVALID_RUN_TIMESTAMP => CronRegistrar::RUNNER_WPCRON,
        self::CLI_INVALID_RUN_TIMESTAMP => CronRegistrar::RUNNER_CRONTAB_CLI,
        self::API_INVALID_RUN_TIMESTAMP => CronRegistrar::RUNNER_CRONTAB_API,
    ];

    const HAS_PROCESSED_YES = 'yes';
    const HAS_PROCESSED_WAITING = 'waiting';
    const HAS_PROCESSED_NO = 'no';

    public static function resetLastExecutionData(): void
    {
        static::set(self::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_UNEXECUTED);
        static::set(self::LAST_EXECUTION_RUNNER, null);
        static::delete(self::LAST_PRE_EXECUTION_DATE);
        static::delete(self::LAST_EXECUTION_HAS_PROCESSED);
        static::set(self::LAST_POST_EXECUTION_STATUS, null);
        static::delete(self::LAST_POST_EXECUTION_ERROR);
        static::delete(self::WPCRON_INVALID_RUN_TIMESTAMP);
        static::delete(self::CLI_INVALID_RUN_TIMESTAMP);
        static::delete(self::API_INVALID_RUN_TIMESTAMP);
    }

    public static function updateHasProcessed(int $beforeCount): void
    {
        if (0 !== $beforeCount) {
            $afterCount = ProcessAllTasks::countNewTasks();
            if ($beforeCount !== $afterCount) {
                self::set(self::LAST_EXECUTION_HAS_PROCESSED, self::HAS_PROCESSED_YES);
            } else {
                self::set(self::LAST_EXECUTION_HAS_PROCESSED, self::HAS_PROCESSED_NO);
            }
        }
    }

    public static function updateFailedExecution(\Throwable $throwable): void
    {
        self::set(self::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_FAILED);
        self::set(self::LAST_POST_EXECUTION_ERROR, $throwable->getMessage());
    }

    public static function updateSuccessfulExecution(): void
    {
        self::set(self::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_SUCCESS);
        self::delete(self::LAST_POST_EXECUTION_ERROR);
    }

    public static function getInvalidRunners(): array
    {
        $runners = CronRegistrar::getCronRunners();
        $invalidRunners = [];
        foreach (self::INVALID_TIMESTAMPS as $option => $runner) {
            $timestamp = self::get($option);
            if (!is_null($timestamp)) {
                $minutesAgo = (time() - $timestamp) / 60;
                if ($minutesAgo <= 5) {
                    $invalidRunners[] = $runners[$runner];
                }
            }
        }

        return $invalidRunners;
    }

    public static function getHasProcessedLabel(string $hasProcessed): string
    {
        switch ($hasProcessed) {
            case self::HAS_PROCESSED_YES:
                return __('Successfully processing tasks', I18N::DOMAIN);
            case self::HAS_PROCESSED_WAITING:
                return __('Waiting for tasks to process', I18N::DOMAIN);
            case self::HAS_PROCESSED_NO:
                return __('Processing tasks does not execute correctly', I18N::DOMAIN);
            default:
                return $hasProcessed;
        }
    }
}
