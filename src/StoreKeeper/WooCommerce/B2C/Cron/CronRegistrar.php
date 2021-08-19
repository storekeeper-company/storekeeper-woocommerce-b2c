<?php

namespace StoreKeeper\WooCommerce\B2C\Cron;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\AbstractOptions;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class CronRegistrar
{
    const HOOK_PROCESS_TASK = AbstractOptions::PREFIX.'-cron-process-task';

    const SCHEDULES_CUSTOM_KEY = 'custom';

    public function addCustomCronInterval($schedules)
    {
        $seconds = StoreKeeperOptions::get(StoreKeeperOptions::CRON_CUSTOM_INTERVAL, 600); // 10 minutes as fallback

        $schedules[self::SCHEDULES_CUSTOM_KEY] = [
            'interval' => $seconds,
            'display' => __('Custom Interval', I18N::DOMAIN),
        ];

        return $schedules;
    }

    public function register()
    {
        if (!wp_next_scheduled(self::HOOK_PROCESS_TASK)) {
            wp_schedule_event(time(), self::SCHEDULES_CUSTOM_KEY, self::HOOK_PROCESS_TASK);
        }
    }
}
