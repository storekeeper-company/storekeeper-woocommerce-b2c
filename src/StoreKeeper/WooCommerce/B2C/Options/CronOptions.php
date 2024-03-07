<?php

namespace StoreKeeper\WooCommerce\B2C\Options;

use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;
use StoreKeeper\WooCommerce\B2C\Exceptions\LockTimeoutException;
use StoreKeeper\WooCommerce\B2C\I18N;

class CronOptions extends AbstractOptions
{
    public const RUNNER = 'cron-runner';
    public const LAST_EXECUTION_STATUS = 'cron-last-execution-status';
    public const LAST_EXECUTION_RUNNER = 'cron-last-execution-runner';
    public const LAST_PRE_EXECUTION_DATE = 'cron-last-pre-execution-date';
    public const LAST_EXECUTION_HAS_PROCESSED = 'cron-last-has-processed';
    public const LAST_POST_EXECUTION_STATUS = 'cron-last-post-execution-status';
    public const LAST_POST_EXECUTION_ERROR = 'cron-last-post-execution-error';
    public const LAST_POST_EXECUTION_ERROR_CLASS = 'cron-last-post-execution-error-class';

    public const INVALID_PREFIX = 'cron-invalid-run-';
    public const WPCRON_INVALID_RUN_TIMESTAMP = self::INVALID_PREFIX.CronRegistrar::RUNNER_WPCRON;
    public const CLI_INVALID_RUN_TIMESTAMP = self::INVALID_PREFIX.CronRegistrar::RUNNER_CRONTAB_CLI;
    public const API_INVALID_RUN_TIMESTAMP = self::INVALID_PREFIX.CronRegistrar::RUNNER_CRONTAB_API;
    public const INVALID_TIMESTAMPS = [
        self::WPCRON_INVALID_RUN_TIMESTAMP => CronRegistrar::RUNNER_WPCRON,
        self::CLI_INVALID_RUN_TIMESTAMP => CronRegistrar::RUNNER_CRONTAB_CLI,
        self::API_INVALID_RUN_TIMESTAMP => CronRegistrar::RUNNER_CRONTAB_API,
    ];

    public const HAS_PROCESSED_YES = 'yes';
    public const HAS_PROCESSED_WAITING = 'waiting';
    public const HAS_PROCESSED_NO = 'no';

    public static function resetLastExecutionData(): void
    {
        static::set(self::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_UNEXECUTED);
        static::set(self::LAST_EXECUTION_RUNNER, null);
        static::delete(self::LAST_PRE_EXECUTION_DATE);
        static::delete(self::LAST_EXECUTION_HAS_PROCESSED);
        static::set(self::LAST_POST_EXECUTION_STATUS, null);
        static::delete(self::LAST_POST_EXECUTION_ERROR);
        static::delete(self::LAST_POST_EXECUTION_ERROR_CLASS);
        static::delete(self::WPCRON_INVALID_RUN_TIMESTAMP);
        static::delete(self::CLI_INVALID_RUN_TIMESTAMP);
        static::delete(self::API_INVALID_RUN_TIMESTAMP);
    }

    public static function updateHasProcessed(): void
    {
        $hasError = get_transient(ProcessAllTasks::HAS_ERROR_TRANSIENT_KEY);

        if (false !== $hasError) {
            if ('no' === $hasError) {
                self::set(self::LAST_EXECUTION_HAS_PROCESSED, self::HAS_PROCESSED_YES);
            } else {
                self::set(self::LAST_EXECUTION_HAS_PROCESSED, self::HAS_PROCESSED_NO);
            }

            delete_transient(ProcessAllTasks::HAS_ERROR_TRANSIENT_KEY);
        }
    }

    public static function updateFailedExecution(\Throwable $throwable): void
    {
        if ($throwable instanceof LockTimeoutException) {
            // do not save the lock exceptions as failed
        } else {
            self::set(self::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_FAILED);
            self::set(self::LAST_POST_EXECUTION_ERROR, $throwable->getMessage());
            self::set(self::LAST_POST_EXECUTION_ERROR_CLASS, get_class($throwable));
        }
    }

    public static function updateSuccessfulExecution(): void
    {
        self::set(self::LAST_EXECUTION_STATUS, CronRegistrar::STATUS_SUCCESS);
        self::delete(self::LAST_POST_EXECUTION_ERROR);
        self::delete(self::LAST_POST_EXECUTION_ERROR_CLASS);
    }

    public static function getInvalidRunners(): array
    {
        $selectedRunner = self::get(self::RUNNER, CronRegistrar::RUNNER_WPCRON);
        $runners = CronRegistrar::getCronRunners();
        $invalidRunners = [];
        foreach (self::INVALID_TIMESTAMPS as $option => $runner) {
            if ($runner !== $selectedRunner) {
                $timestamp = self::get($option);
                if (!is_null($timestamp)) {
                    $minutesAgo = (time() - $timestamp) / 60;
                    if ($minutesAgo <= 5) {
                        $invalidRunners[] = $runners[$runner];
                    }
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
