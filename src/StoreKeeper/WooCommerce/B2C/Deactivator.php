<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Cron\CronRegistrar;

class Deactivator
{
    public function run()
    {
        wp_clear_scheduled_hook(CronRegistrar::HOOK_PROCESS_TASK);
    }
}
