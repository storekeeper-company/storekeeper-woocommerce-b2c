<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Regression;

use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\AbstractTest;
use WC_Helper_Product;

class ProductRemovalErrorsTest extends AbstractTest
{
    /**
     * @var \mysqli
     */
    protected $connection;

    public function testRemovalOfChildWithoutParentSchedulesRecalculationTask()
    {
        $this->initApiConnection();

        $parentStorekeeperId = rand();
        $childStorekeeperId = rand();

        $parentProduct = WC_Helper_Product::create_variation_product();
        update_post_meta($parentProduct->get_id(), 'storekeeper_id', $parentStorekeeperId);

        $childProduct = wc_get_product($parentProduct->get_children()[0]);
        update_post_meta($childProduct->get_id(), 'storekeeper_id', $childStorekeeperId);

        $this->assertCount(0, $this->getTaskToProcess()); //confirm there are none

        TaskHandler::scheduleTask(
            TaskHandler::PRODUCT_DELETE,
            $parentStorekeeperId,
            ['storekeeper_id' => $parentStorekeeperId]
        );
        TaskHandler::scheduleTask(
            TaskHandler::PRODUCT_DELETE,
            $childStorekeeperId,
            ['storekeeper_id' => $childStorekeeperId]
        );

        $this->assertCount(2, $this->getTaskToProcess()); //confirm there are 2 now

        $this->runner->execute(ProcessAllTasks::getCommandName());

        $this->assertCount(
            0,
            $this->getTaskToProcess(),
            'Deletion created tasks to process'
        );
    }

    public function testDeactivationOfChildWithoutParentSchedulesRecalculationTask()
    {
        $this->initApiConnection();

        $parentStorekeeperId = rand();
        $childStorekeeperId = rand();

        $parentProduct = WC_Helper_Product::create_variation_product();
        update_post_meta($parentProduct->get_id(), 'storekeeper_id', $parentStorekeeperId);

        $childProduct = wc_get_product($parentProduct->get_children()[0]);
        update_post_meta($childProduct->get_id(), 'storekeeper_id', $childStorekeeperId);

        $this->assertCount(0, $this->getTaskToProcess()); //confirm there are none

        TaskHandler::scheduleTask(
            TaskHandler::PRODUCT_DEACTIVATED,
            $parentStorekeeperId,
            ['storekeeper_id' => $parentStorekeeperId]
        );
        TaskHandler::scheduleTask(
            TaskHandler::PRODUCT_DEACTIVATED,
            $childStorekeeperId,
            ['storekeeper_id' => $childStorekeeperId]
        );

        $this->assertCount(2, $this->getTaskToProcess()); //confirm there are 2 now

        $this->runner->execute(ProcessAllTasks::getCommandName());

        $this->assertCount(
            0,
            $this->getTaskToProcess(),
            'Deactivation of created tasks to process'
        );
    }

    protected function getTaskToProcess()
    {
        return array_merge(
            ProcessAllTasks::getOrderTaskIds(),
            ProcessAllTasks::getNonOrderTaskIds()
        );
    }
}
