<?php

namespace StoreKeeper\WooCommerce\B2C\Cron;

use StoreKeeper\WooCommerce\B2C\Exceptions\CronRunnerException;
use StoreKeeper\WooCommerce\B2C\Exceptions\InvalidRunnerException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\AbstractOptions;
use StoreKeeper\WooCommerce\B2C\Options\CronOptions;

class CronRegistrar
{
    public const HOOK_PROCESS_TASK = AbstractOptions::PREFIX.'-cron-process-task';

    public const SCHEDULES_EVERY_MINUTE_KEY = 'every_minute';

    public const RUNNER_WPCRON = 'wp-cron';
    public const RUNNER_CRONTAB_API = 'crontab-api';
    public const RUNNER_CRONTAB_CLI = 'crontab-cli';
    public const CRONTAB_RUNNERS = [
        self::RUNNER_CRONTAB_CLI,
        self::RUNNER_CRONTAB_API,
    ];

    public const STATUS_UNEXECUTED = 'unexecuted';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SUCCESS = 'success';

    public function addCustomCronInterval($schedules): array
    {
        $schedules[self::SCHEDULES_EVERY_MINUTE_KEY] = [
            'interval' => 60,
            'display' => __('Every minute', I18N::DOMAIN),
        ];

        return $schedules;
    }

    public function register(): void
    {
        $runner = CronOptions::get(CronOptions::RUNNER, self::RUNNER_WPCRON);
        if (self::RUNNER_WPCRON === $runner && !wp_next_scheduled(self::HOOK_PROCESS_TASK)) {
            wp_schedule_event(time(), self::SCHEDULES_EVERY_MINUTE_KEY, self::HOOK_PROCESS_TASK);
        }
    }

    /**
     * @throws CronRunnerException
     */
    public static function checkRunnerStatus(): void
    {
        $runner = CronOptions::get(CronOptions::RUNNER, self::RUNNER_WPCRON);

        if (self::RUNNER_WPCRON === $runner) {
            static::checkWpCronStatus();
        }
    }

    /**
     * Forked from WP Crontrol v1.10.0.
     *
     * @see https://wordpress.org/plugins/wp-crontrol test_cron_spawn()
     *
     * @throws CronRunnerException
     */
    private static function checkWpCronStatus(): void
    {
        global $wp_version;

        $sslVerify = version_compare($wp_version, 4.0, '<');
        $lockTime = sprintf('%.22F', microtime(true));

        $cronRequest = apply_filters('cron_request', [
            'url' => add_query_arg('doing_wp_cron', $lockTime, site_url('wp-cron.php')),
            'key' => $lockTime,
            'args' => [
                'timeout' => 3,
                'blocking' => true,
                'sslverify' => apply_filters('https_local_ssl_verify', $sslVerify),
            ],
        ]);

        $cronResponse = wp_remote_post($cronRequest['url'], $cronRequest['args']);

        if (is_wp_error($cronResponse)) {
            throw new CronRunnerException(sprintf(__('WP Cron encountered an error and may not work: %s', I18N::DOMAIN), strip_tags($cronResponse->get_error_message())));
        } elseif (wp_remote_retrieve_response_code($cronResponse) >= 300) {
            throw new CronRunnerException(sprintf(__('WP Cron return an unexpected HTTP response code: %s', I18N::DOMAIN), (int) wp_remote_retrieve_response_code($cronResponse)));
        }
    }

    public static function buildMessage(): array
    {
        $message = __(
            'StoreKeeper task processing is not being executed:',
            I18N::DOMAIN
        );

        $schedulerSettingsLink = '/wp-admin/admin.php?page=storekeeper-settings&tab=scheduler';
        $schedulerSettingsText = __('Scheduler settings', I18N::DOMAIN);
        $description = sprintf(__('To see guide on how to configure cron, navigate to %s.', I18N::DOMAIN), "<a href='{$schedulerSettingsLink}'>{$schedulerSettingsText}</a>")
            .' '.__('Contact your system administrator if the problem persists.', I18N::DOMAIN);

        return [
            $message,
            $description,
        ];
    }

    public static function getCronRunners(): array
    {
        return [
            self::RUNNER_WPCRON => __('Wordpress Cron (slowest)', I18N::DOMAIN),
            self::RUNNER_CRONTAB_API => __('Crontab with curl API calls', I18N::DOMAIN),
            self::RUNNER_CRONTAB_CLI => __('Crontab with wp-cli (fastest)', I18N::DOMAIN),
        ];
    }

    /**
     * Validates if the runner that executes the task
     * processing cron is the one that is configured.
     *
     * @throws InvalidRunnerException
     */
    public static function validateRunner(string $actualRunner): void
    {
        $runner = CronOptions::get(CronOptions::RUNNER, self::RUNNER_WPCRON);
        $cronRunners = self::getCronRunners();
        if ($actualRunner !== $runner) {
            CronOptions::set(CronOptions::INVALID_PREFIX.$actualRunner, time());
            $message = sprintf(__('Task scheduler is set to [%s], change to [%s] to use this.', I18N::DOMAIN), $cronRunners[$runner], $cronRunners[$actualRunner]);
            throw new InvalidRunnerException($message);
        }
    }

    public static function getStatusLabel(string $status): string
    {
        switch ($status) {
            case self::STATUS_UNEXECUTED:
                return __('Not performed yet.', I18N::DOMAIN);
            case self::STATUS_SUCCESS:
                return __('Last cron run was successful.', I18N::DOMAIN);
            case self::STATUS_FAILED:
                return __('Last cron run failed.', I18N::DOMAIN);
            default:
                return $status;
        }
    }
}
