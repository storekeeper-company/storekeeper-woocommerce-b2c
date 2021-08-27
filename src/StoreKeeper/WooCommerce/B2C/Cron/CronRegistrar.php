<?php

namespace StoreKeeper\WooCommerce\B2C\Cron;

use StoreKeeper\WooCommerce\B2C\Exceptions\CronRunnerException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\AbstractOptions;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class CronRegistrar
{
    public const HOOK_PROCESS_TASK = AbstractOptions::PREFIX.'-cron-process-task';

    public const SCHEDULES_EVERY_MINUTE_KEY = 'every_minute';

    public const RUNNER_WPCRON = 'wp-cron';
    public const RUNNER_WPCRON_CRONTAB = 'wp-cron-crontab';
    public const RUNNER_CRONTAB_API = 'crontab-api';
    public const CRON_RUNNERS = [
        self::RUNNER_WPCRON => 'Wordpress Cron',
        self::RUNNER_WPCRON_CRONTAB => 'Wordpress Cron (via Crontab)',
        self::RUNNER_CRONTAB_API => 'Server Crontab (via API call)',
    ];
    public const CRONTAB_RUNNERS = [
        self::RUNNER_WPCRON_CRONTAB,
        self::RUNNER_CRONTAB_API,
    ];
    public const WP_CRON_RUNNERS = [
        self::RUNNER_WPCRON,
        self::RUNNER_WPCRON_CRONTAB,
    ];

    public const REQUIRED_EXTENSIONS = [
        'curl',
    ];

    public function setup()
    {
        $cronRunner = StoreKeeperOptions::get(StoreKeeperOptions::CRON_RUNNER, self::RUNNER_WPCRON);
        if (self::RUNNER_WPCRON_CRONTAB === $cronRunner && !defined('DISABLE_WP_CRON')) {
            define('DISABLE_WP_CRON', true);
        }
    }

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
        $cronRunner = StoreKeeperOptions::get(StoreKeeperOptions::CRON_RUNNER, self::RUNNER_WPCRON);
        if (in_array($cronRunner, self::WP_CRON_RUNNERS, true) && !wp_next_scheduled(self::HOOK_PROCESS_TASK)) {
            wp_schedule_event(time(), self::SCHEDULES_EVERY_MINUTE_KEY, self::HOOK_PROCESS_TASK);
        }
    }

    /**
     * @throws CronRunnerException
     */
    public static function checkRunnerStatus(): void
    {
        $runner = StoreKeeperOptions::get(StoreKeeperOptions::CRON_RUNNER);

        switch ($runner) {
            case self::RUNNER_WPCRON_CRONTAB:
                static::checkWpCronStatus(true);
                static::checkExtensions();
                static::checkCrontabStatus();
                break;
            case self::RUNNER_CRONTAB_API:
                static::checkExtensions();
                static::checkCrontabStatus();
                break;
            default:
                static::checkWpCronStatus();
                break;
        }
    }

    /**
     * @throws CronRunnerException
     */
    private static function checkExtensions(): void
    {
        $extensions = get_loaded_extensions();
        $missingExtensions = array_diff(self::REQUIRED_EXTENSIONS, $extensions);
        if (!empty($missingExtensions)) {
            throw new CronRunnerException(sprintf(__('The cron may not work properly due to these missing extensions: %s', I18N::DOMAIN), implode(', ', $missingExtensions)));
        }
    }

    /**
     * @throws CronRunnerException
     */
    private static function checkCrontabStatus(): void
    {
        try {
            $cronService = shell_exec('service cron status');
            if (is_null($cronService)) {
                throw new \Exception(sprintf(__('%s is not installed in the server. Task processing cron may not work.', I18N::DOMAIN), 'Cron'));
            }
        } catch (\Throwable $throwable) {
            throw new CronRunnerException($throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    /**
     * Forked from WP Crontrol v1.10.0.
     *
     * @see https://wordpress.org/plugins/wp-crontrol test_cron_spawn()
     *
     * @throws CronRunnerException
     */
    private static function checkWpCronStatus(bool $disableCron = false): void
    {
        global $wp_version;

        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON !== $disableCron) {
            throw new CronRunnerException(sprintf(__('The %s constant is set to %s. Task processing cron may experience conflict or not work at all.', I18N::DOMAIN), 'DISABLE_WP_CRON', DISABLE_WP_CRON ? 'true' : 'false'));
        }

        if (defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON) {
            throw new CronRunnerException(sprintf(__('The %s constant is set to true.', I18N::DOMAIN), 'ALTERNATE_WP_CRON'));
        }

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
            throw new CronRunnerException(sprintf(esc_html__('WP Cron encountered an error and may not work: %s', I18N::DOMAIN), '</p><p><strong>'.esc_html($cronResponse->get_error_message()).'</strong>'));
        } elseif (wp_remote_retrieve_response_code($cronResponse) >= 300) {
            throw new CronRunnerException(sprintf(__('WP Cron return an unexpected HTTP response code: %s', I18N::DOMAIN), (int) wp_remote_retrieve_response_code($cronResponse)));
        }
    }

    public static function buildMessage(): array
    {
        $runner = StoreKeeperOptions::get(StoreKeeperOptions::CRON_RUNNER);

        $message = __(
            'Cron is not running:',
            I18N::DOMAIN
        );

        $description = __('Contact your system administrator to solve this problem', I18N::DOMAIN);

        if (in_array($runner, self::CRONTAB_RUNNERS, true)) {
            $message = __(
                'Please configure as your cron tab:',
                I18N::DOMAIN
            );

            if (self::RUNNER_WPCRON_CRONTAB === $runner) {
                $siteUrl = site_url('wp-cron.php');
            } else {
                $siteUrl = site_url('process-tasks');
            }

            $path = esc_html(ABSPATH);
            $description = <<<HTML
            <p style="white-space: pre-line;">*/1 * * * * curl {$siteUrl}
            0 3 * * * /usr/local/bin/wp sk sync-issue-check --path={$path} --skip-plugins=wp-optimize
            </p>
HTML;
        }

        return [
            $message,
            $description,
        ];
    }
}
