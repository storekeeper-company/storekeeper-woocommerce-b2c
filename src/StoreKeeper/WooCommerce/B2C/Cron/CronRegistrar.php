<?php

namespace StoreKeeper\WooCommerce\B2C\Cron;

use StoreKeeper\WooCommerce\B2C\Endpoints\EndpointLoader;
use StoreKeeper\WooCommerce\B2C\Endpoints\TaskProcessor\TaskProcessorEndpoint;
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
    public const CRONTAB_RUNNERS = [
        self::RUNNER_WPCRON_CRONTAB,
        self::RUNNER_CRONTAB_API,
    ];
    public const WP_CRON_RUNNERS = [
        self::RUNNER_WPCRON,
        self::RUNNER_WPCRON_CRONTAB,
    ];

    public function setup(): void
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

        if (in_array($runner, self::WP_CRON_RUNNERS, true)) {
            static::checkWpCronStatus(true);
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
            'Cron may not be running:',
            I18N::DOMAIN
        );

        $description = __('Contact your system administrator if the problem persists', I18N::DOMAIN);

        if (in_array($runner, self::CRONTAB_RUNNERS, true)) {
            $message = __(
                'Please configure as your cron tab:',
                I18N::DOMAIN
            );

            if (self::RUNNER_WPCRON_CRONTAB === $runner) {
                $url = site_url('wp-cron.php');
            } else {
                $url = rest_url(EndpointLoader::getFullNamespace().'/'.TaskProcessorEndpoint::ROUTE);
            }

            $description = <<<HTML
            <p style="white-space: pre-line;">*/1 * * * * curl {$url}</p>
HTML;
        }

        return [
            $message,
            $description,
        ];
    }

    public static function getCronRunners(): array
    {
        return [
            self::RUNNER_WPCRON => __('Wordpress Cron', I18N::DOMAIN),
            self::RUNNER_WPCRON_CRONTAB => __('Wordpress Cron (via Crontab)', I18N::DOMAIN),
            self::RUNNER_CRONTAB_API => __('Server Crontab (via API call)', I18N::DOMAIN),
        ];
    }
}
