<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Tools;

use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\Tools\TaskRateCalculator;
use StoreKeeper\WooCommerce\B2C\UnitTest\AbstractTest;

class TaskRateCalculatorTest extends AbstractTest
{
    public function testEmptyTasks()
    {
        $now = '1970-01-01 02:00:00';
        $calculator = new TaskRateCalculator($now);
        $incomingRate = $calculator->countIncoming();
        $processedRate = $calculator->calculateProcessed();

        $this->assertEquals(0, $incomingRate, 'Should not return error but 0');
        $this->assertEquals(0, $processedRate, 'Should not return error but 0');
    }

    public function testTaskIncomingRate()
    {
        // make a task
        $this->createTaskWithCreatedDate(1, '1970-01-01 01:30:00');
        $this->createTaskWithCreatedDate(2, '1970-01-01 01:45:00');
        $now = '1970-01-01 02:00:00';

        $calculator = new TaskRateCalculator($now);
        $incomingRate = $calculator->countIncoming();

        $this->assertEquals(2, $incomingRate, 'Expected 2 incoming rate per hour for 2 task within 1 hour');
    }

    public function testTaskProcessedRate()
    {
        $task1 = $this->createTaskWithProcessedDate(1, '1970-01-01 01:30:00');
        $task2 = $this->createTaskWithProcessedDate(2, '1970-01-01 01:45:00');

        $task1['execution_duration'] = 1.00; // 1 second execution
        TaskModel::update($task1['id'], $task1);

        $task2['execution_duration'] = 1.00; // 1 second execution
        TaskModel::update($task2['id'], $task2);

        $now = '1970-01-01 02:00:00';

        $calculator = new TaskRateCalculator($now);
        $processedRate = $calculator->calculateProcessed();

        $this->assertEquals(3600.0, $processedRate, 'Expected 3600 processing rate per hour for 2 tasks with 1 second execution');
    }

    public function createTaskWithProcessedDate($id, string $processedDate)
    {
        $task = TaskHandler::scheduleTask(
            TaskHandler::PRODUCT_IMPORT,
            $id,
            ['storekeeper_id' => $id],
            true
        );

        $task['date_last_processed'] = $processedDate;
        TaskModel::update($task['id'], $task);

        return $task;
    }

    public function createTaskWithCreatedDate($id, string $createdDate)
    {
        $task = TaskHandler::scheduleTask(
            TaskHandler::PRODUCT_IMPORT,
            $id,
            ['storekeeper_id' => $id],
            true
        );

        $task['date_created'] = $createdDate;
        TaskModel::update($task['id'], $task);

        return $task;
    }
}
