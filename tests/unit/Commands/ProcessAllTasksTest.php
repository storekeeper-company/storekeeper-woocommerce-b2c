<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Query\CronQueryBuilder;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class ProcessAllTasksTest extends AbstractTest
{
    use CommandRunnerTrait;

    public const MISSING_TASK_ERROR_DIR = 'commands/process-all-tasks/missing-task-error';
    public const GET_CONFIGURABLE_SHOP_PRODUCT_OPTIONS_FILE = '20200515_045528.moduleFunction.ShopModule::getConfigurableShopProductOptions.success.5ebe20bfe98de.json';

    public function testLastRunTimeVariableSet()
    {
        $db = new DatabaseConnection();

        $this->assertFalse(
            $this->hasLastRunTime($db),
            'Last sync time exists'
        );

        try {
            $this->runner->execute(ProcessAllTasks::getCommandName());
        } catch (\Throwable $e) {
            $this->assertTrue(true, 'Unable to run the process all command');
        }

        $this->assertTrue(
            $this->hasLastRunTime($db),
            'Last sync time does not exists'
        );
    }

    private function hasLastRunTime(DatabaseConnection $db)
    {
        $result = $db->querySql(CronQueryBuilder::getCountLastRunTimeSql());
        $row = $result->fetch_row();

        return !empty($row) && count($row) > 0 && $row[0] > 0;
    }

    /**
     * @throws WordpressException
     * @throws \Throwable
     */
    public function testUpdateProductError()
    {
        $this->initApiConnection();

        // make a broken task
        $id = -1;
        $task = TaskHandler::scheduleTask(
            TaskHandler::PRODUCT_IMPORT,
            $id,
            ['storekeeper_id' => $id],
            true
        );

        // process the tasks
        try {
            $this->runner->execute(ProcessAllTasks::getCommandName());
            $this->assertTrue(false, 'Exception is thrown');
        } catch (\Throwable $e) {
            $this->assertTrue(true, 'Exception is thrown');
        }

        // Renew the task
        $task = TaskModel::get($task['id']);
        $this->assertEquals(TaskHandler::STATUS_FAILED, $task['status'], 'task marked as failed');
    }

    /**
     * This test simulates a missing task, where it needs to show a warning that it was skipped.
     */
    public function testMissingTaskError()
    {
        $this->initApiConnection();

        $mockResponse = [
            'success' => true,
            'return' => [
                'data' => [
                    [
                        'translatable' => [
                            'id' => 10,
                            'lang' => 'nl',
                            'used_langs' => [],
                            'translated_langs' => [],
                            'reviewed_langs' => [],
                            'final_langs' => [],
                            'date_created' => '2020-01-29 10:53:53+01:00',
                            'backref' => 'BlogModule::Attribute(id=1)',
                            'translatable_type_id' => 6,
                        ],
                        'id' => 2,
                        'name' => 'brand',
                        'label' => 'Brand',
                        'relation_data_id' => 2,
                        'is_options' => true,
                        'required' => false,
                        'published' => true,
                        'date_created' => '2020-01-29 10:53:53+01:00',
                        'type' => 'string',
                        'configuration_id' => 1,
                        'translatable_id' => 10,
                        'date_updated' => '2020-01-29 10:53:53+01:00',
                        'unique' => false,
                        'order' => 0,
                    ],
                    [
                        'translatable' => [
                            'id' => 2,
                            'lang' => 'en',
                            'used_langs' => [],
                            'translated_langs' => [],
                            'reviewed_langs' => [],
                            'final_langs' => [],
                            'date_created' => '2024-01-01 10:00:00+00:00',
                            'backref' => 'BlogModule::Attribute(id=2)',
                            'translatable_type_id' => 1
                        ],
                        'id' => 2,
                        'name' => 'size',
                        'label' => 'Size',
                        'relation_data_id' => 1,
                        'is_options' => true,
                        'required' => false,
                        'published' => true,
                        'date_created' => '2024-01-01 10:00:00+00:00',
                        'type' => 'string',
                        'configuration_id' => 1,
                        'translatable_id' => 2,
                        'date_updated' => '2024-01-01 10:00:00+00:00',
                        'unique' => false,
                        'order' => 1
                    ],
                ],
                'total' => 8,
                'count' => 8,
            ],
            '_type' => 'moduleFunction',
            'time_ms' => 100,
            '_version' => '1.0',
            '_timestamp' => now()->toISOString(),
        ];

        $this->mockApiCall('moduleFunction.BlogModule::listTranslatedAttributes', $mockResponse);

        // Plan tasks
        $this->processEventsFromDir(self::MISSING_TASK_ERROR_DIR.'/events');

        // Plan a recalculation task based on the parent shop product id
        $dumpFile = $this->getDataDump(
            self::MISSING_TASK_ERROR_DIR.'/dump/'.self::GET_CONFIGURABLE_SHOP_PRODUCT_OPTIONS_FILE
        );
        $return = $dumpFile->getReturn();
        $shopProductId = $return['configurable_shop_product']['shop_product_id'];

        $taskHandler = new TaskHandler();
        $taskHandler->rescheduleTask(
            TaskHandler::PARENT_PRODUCT_RECALCULATION,
            "shop_product_id::$shopProductId",
            [
                'parent_shop_product_id' => $shopProductId,
            ]
        );

        // Run processing of tasks
        $this->mockApiCallsFromDirectory(self::MISSING_TASK_ERROR_DIR.'/dump', true);
        $this->mockApiCallsFromCommonDirectory();
        $this->mockMediaFromDirectory(self::MISSING_TASK_ERROR_DIR.'/dump/media');

        $this->runner->execute(ProcessAllTasks::getCommandName());

        $this->assertFalse(
            $this->logger->hasErrorThatContains(ProcessAllTasks::MASSAGE_TASK_FAILED),
            'Task failed: '.json_encode($this->logger->recordsByLevel['error'] ?? null)
        );
        $this->assertTrue(
            $this->logger->hasNoticeThatContains('skipping'),
            'Did not skipped the task'
        );
    }

    private function processEventsFromDir($dir)
    {
        $eventDir = $this->getDataDir().'/'.$dir;
        $jsonFiles = array_diff(scandir($eventDir), ['..', '.']);
        sort($jsonFiles);

        foreach ($jsonFiles as $jsonFile) {
            $jsonPath = $dir.'/'.$jsonFile;
            $file = $this->getHookDataDump($jsonPath);
            $request = $this->getRestWithToken($file);
            $response = $this->handleRequest($request);
            $data = $response->get_data();
            $this->assertTrue($data['success'], 'Request failed');
        }
    }
}
