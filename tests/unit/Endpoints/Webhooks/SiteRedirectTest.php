<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\RedirectHandler;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\CommandRunnerTrait;
use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;

class SiteRedirectTest extends AbstractTest
{
    use CommandRunnerTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpRunner();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->tearDownRunner();
    }

    const CREATE_DATADUMP_DIRECTORY = 'events/createRedirect';
    const CREATE_DATADUMP_HOOK = 'events/hook.events.createRedirect.json';
    const CREATE_DATADUMP_SOURCE_FILE = 'moduleFunction.ShopModule::listSiteRedirectsForHooks.success.json';

    const UPDATE_DATADUMP_DIRECTORY = 'events/updateRedirect';
    const UPDATE_DATADUMP_HOOK = 'events/hook.events.updateRedirect.json';
    const UPDATE_DATADUMP_SOURCE_FILE = 'moduleFunction.ShopModule::listSiteRedirectsForHooks.success.json';

    const DELETE_DATADUMP_DIRECTORY = 'events/deleteRedirect';
    const DELETE_DATADUMP_HOOK = 'events/hook.events.deleteRedirect.json';

    public function testCreate()
    {
        $this->initApiConnection();

        $this->createRedirect();

        $file = $this->getDataDump(self::CREATE_DATADUMP_DIRECTORY.'/'.self::CREATE_DATADUMP_SOURCE_FILE);
        $original_data = $file->getReturn()['data'][0];
        $original = new Dot($original_data);

        $current_data = RedirectHandler::getRedirect($original->get('id'));
        $current = new Dot($current_data);

        $this->assertRedirects($original, $current);

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testUpdate()
    {
        $this->initApiConnection();

        $this->createRedirect();

        $this->updateRedirect();

        $file = $this->getDataDump(self::UPDATE_DATADUMP_DIRECTORY.'/'.self::UPDATE_DATADUMP_SOURCE_FILE);
        $original_data = $file->getReturn()['data'][0];
        $original = new Dot($original_data);

        $current_data = RedirectHandler::getRedirect($original->get('id'));
        $current = new Dot($current_data);

        $this->assertRedirects($original, $current);

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testDelete()
    {
        $this->initApiConnection();

        $this->createRedirect();

        $this->deleteRedirect();

        $file = $this->getDataDump(self::CREATE_DATADUMP_DIRECTORY.'/'.self::CREATE_DATADUMP_SOURCE_FILE);
        $create_data = $file->getReturn()['data'][0];
        $create = new Dot($create_data);

        $this->assertEmpty(
            RedirectHandler::getRedirect($create->get('id')),
            'Redirect deleted'
        );

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testOrderOnlySyncMode()
    {
        $this->initApiConnection();

        StoreKeeperOptions::set(StoreKeeperOptions::SYNC_MODE, StoreKeeperOptions::SYNC_MODE_ORDER_ONLY);

        $this->createRedirect(false);
        $this->assertTaskCount(0, 'No tasks are supposed to be created');
        $this->runner->execute(ProcessAllTasks::getCommandName());

        $this->updateRedirect(false);
        $this->assertTaskCount(0, 'No tasks are supposed to be created');
        $this->runner->execute(ProcessAllTasks::getCommandName());

        $file = $this->getDataDump(self::UPDATE_DATADUMP_DIRECTORY.'/'.self::UPDATE_DATADUMP_SOURCE_FILE);
        $original_data = $file->getReturn()['data'][0];
        $original = new Dot($original_data);

        $current_data = RedirectHandler::getRedirect($original->get('id'));

        $this->assertNull($current_data, 'Site redirect should not be imported');
    }

    private function assertRedirects($original, $current)
    {
        $this->assertEquals(
            $original->get('id'),
            $current->get('storekeeper_id'),
            'Redirect ID imported'
        );
        $this->assertEquals(
            $original->get('from_url'),
            $current->get('from_url'),
            'Redirect from url imported'
        );
        $this->assertEquals(
            $original->get('to_url'),
            $current->get('to_url'),
            'Redirect to url imported'
        );
        $this->assertEquals(
            $original->get('http_status_code'),
            $current->get('status_code'),
            'Redirect http code imported'
        );
    }

    private function createRedirect(bool $process_tasks = true)
    {
        $this->runAndExecuteHook(self::CREATE_DATADUMP_DIRECTORY, self::CREATE_DATADUMP_HOOK, $process_tasks);
    }

    private function updateRedirect(bool $process_tasks = true)
    {
        $this->runAndExecuteHook(self::UPDATE_DATADUMP_DIRECTORY, self::UPDATE_DATADUMP_HOOK, $process_tasks);
    }

    private function deleteRedirect()
    {
        $this->runAndExecuteHook(self::DELETE_DATADUMP_DIRECTORY, self::DELETE_DATADUMP_HOOK);
    }

    private function runAndExecuteHook($mock_dir, $hook_file, bool $process_tasks = true)
    {
        // Setup mock dir
        $this->mockApiCallsFromDirectory($mock_dir, true);

        // Get hook file
        $hookFile = $this->getHookDataDump($hook_file);

        // Get rest response & create tasks
        $rest = $this->getRestWithToken($hookFile);
        $response = $this->handleRequest($rest);
        $response = $response->get_data();

        // Get hook_type
        list($hook_type) = StoreKeeperApi::extractMainTypeAndOptions($hookFile->getEventBackref());

        // Tests
        $this->assertTrue(
            $response['success'],
            'Hook call successfull'
        );
        $this->assertEquals(
            'events',
            $hookFile->getHookAction(),
            'Hook actions'
        );
        $this->assertEquals(
            'BlogModule::SiteRedirect',
            $hook_type,
            'Correct hook type'
        );

        if ($process_tasks) {
            $this->runner->execute(ProcessAllTasks::getCommandName());
        }
    }
}
