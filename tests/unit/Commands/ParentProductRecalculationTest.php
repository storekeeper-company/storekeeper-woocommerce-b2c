<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use Mockery\MockInterface;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class ParentProductRecalculationTest extends AbstractTest
{
    /**
     * @throws \Throwable
     */
    public function testParentProductRecalculationFailing()
    {
        $this->initApiConnection();

        // Setup the ShopModule::naturalSearchShopFlatProductForHooks so it returns an empty data object
        StoreKeeperApi::$mockAdapter->withModule(
            'ShopModule',
            function (MockInterface $module) {
                $module->shouldReceive('naturalSearchShopFlatProductForHooks')->andReturnUsing(
                    function ($got) {
                        return ['data' => []];
                    }
                );
            }
        );

        // make a broken task
        $parent_id = -1;
        $task = TaskHandler::scheduleTask(
            TaskHandler::PARENT_PRODUCT_RECALCULATION,
            $parent_id,
            [
                'parent_shop_product_id' => $parent_id,
            ],
            true
        );

        /*
         * Example taken from: https://github.com/sebastianbergmann/phpunit-documentation/issues/171#issuecomment-304431935
         */
        $this->runner->execute(ProcessAllTasks::getCommandName());
        $this->assertTrue(true, 'Task ran without exceptions'); // No error has been thrown

        // Update task data
        $task = TaskModel::get($task['id']);

        $this->assertEquals(TaskHandler::STATUS_SUCCESS, $task['status'], 'error: '.json_encode($task));
    }
}
