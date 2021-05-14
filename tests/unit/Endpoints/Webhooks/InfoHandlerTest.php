<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks\InfoHandler;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;

class InfoHandlerTest extends AbstractTest
{
    /**
     * test if info works.
     *
     * @throws \Throwable
     */
    public function testHandleOk()
    {
        $file = $this->getHookDataDump('hook.info.json');
        $rest = $this->getRestWithToken($file);
        $this->assertEquals('info', $file->getHookAction());

        $response = $this->handleRequest($rest);
        $data = $response->get_data();
        $extra = $data['extra'];

        $this->assertEquals(
            get_bloginfo('version'),
            $data['platform_version'],
            'Incorrect platform version'
        );

        /*
         * Check extra fields
         */
        $this->assertNotEmpty(
            $extra,
            'Missing extra fields'
        );
        foreach (InfoHandler::EXTRA_BLOG_INFO_FIELDS as $field) {
            $this->assertEquals(
                get_bloginfo($field),
                $extra[$field],
                "Check blog info field $field"
            );
        }

        /*
         * Check active theme
         */
        $actualTheme = $extra['active_theme'];
        $this->assertNotEmpty(
            $actualTheme,
            'Missing active_theme in extra fields'
        );
        $expectedTheme = wp_get_theme();
        foreach (InfoHandler::EXTRA_ACTIVE_THEME_FIELD as $field) {
            $this->assertEquals($expectedTheme->get($field), $actualTheme[$field]);
        }

        /*
         * Check sync mode
         */
        $this->assertEquals(
            StoreKeeperOptions::SYNC_MODE_FULL_SYNC,
            $extra['sync_mode'],
            'Incorrect sync mode'
        );

        StoreKeeperOptions::set(StoreKeeperOptions::SYNC_MODE, StoreKeeperOptions::SYNC_MODE_ORDER_ONLY);

        $response = $this->handleRequest($rest);
        $data = $response->get_data();
        $extra = $data['extra'];

        $this->assertEquals(
            StoreKeeperOptions::SYNC_MODE_ORDER_ONLY,
            $extra['sync_mode'],
            'Incorrect sync mode'
        );

        /*
         * Check sync stats
         */
        $this->assertEquals(
            InfoHandler::getLastSyncRunDate(),
            $extra['last_sync_run_date'],
            'Incorrect last_sync_run_date'
        );
        $this->assertEquals(
            InfoHandler::getLastHookDate(),
            $extra['last_hook_date'],
            'Incorrect last_hook_date'
        );
        $this->assertEquals(
            TaskModel::countTasks(),
            $extra['task_quantity'],
            'Incorrect task_quantity'
        );
        $this->assertEquals(
            WebhookLogModel::count(),
            $extra['hook_quantity'],
            'Incorrect hook_quantity'
        );
        $this->assertEquals(
            InfoHandler::getLastWebhookLogId(),
            $extra['last_hook_id'],
            'Incorrect last_hook_id'
        );
        $this->assertEquals(
            InfoHandler::getLastTaskId(),
            $extra['last_task_id'],
            'Incorrect last_task_id'
        );
        $this->assertEquals(
            TaskModel::countFailedTasks(),
            $extra['task_failed_quantity'],
            'Incorrect task_failed_quantity'
        );
        $this->assertEquals(
            TaskModel::countSuccessfulTasks(),
            $extra['task_successful_quantity'],
            'Incorrect task_successful_quantity'
        );
    }
}
