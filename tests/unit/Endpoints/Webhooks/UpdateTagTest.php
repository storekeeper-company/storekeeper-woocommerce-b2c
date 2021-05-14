<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;

class UpdateTagTest extends AbstractTest
{
    const CREATE_DATADUMP_DIRECTORY = 'events/createTag';
    const CREATE_DATADUMP_HOOK = 'events/hook.events.createTag.json';
    const CREATE_DATADUMP_SOURCE_FILE = '20200219_144448.moduleFunction.ShopModule::listTranslatedCategoryForHooks.success.5e4d49dfd207d.json';

    const UPDATE_DATADUMP_DIRECTORY = 'events/updateTag';
    const UPDATE_DATADUMP_HOOK = 'events/hook.events.updateTag.json';
    const UPDATE_DATADUMP_SOURCE_FILE = '20200220_133725.moduleFunction.ShopModule::listTranslatedCategoryForHooks.success.5e4e8b956c4ec.json';

    const DELETE_DATADUMP_DIRECTORY = 'events/deleteTag';
    const DELETE_DATADUMP_HOOK = 'events/hook.events.deleteTag.json';

    /**
     * Test if creating a tag works.
     *
     * @throws \Throwable
     */
    public function testCreateTag()
    {
        $this->initApiConnection();

        $this->createTag();

        $used_keys = StoreKeeperApi::$mockAdapter->getUsedReturns();
        $this->assertCount(2, $used_keys, 'Not all calls were made');

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    /**
     * Test if updating a tag works.
     *
     * @throws WordpressException
     * @throws \Throwable
     */
    public function testUpdateTag()
    {
        $this->initApiConnection();

        list($created_tag, $created_options) = $this->createTag();
        list($updated_tag, $updated_options, $original_updated) = $this->updateTag();

        $this->assertNotEquals($updated_tag, $created_tag, 'Updated tags were the same, so could never be updated');

        // Check if the ids are the same
        $this->assertEquals($updated_options['id'], $created_options['id'], 'Updated tags have different ids');

        $this->updateChanged($updated_tag, $created_tag, $original_updated);

        // Make sure 4 requests have been made
        $used_keys = StoreKeeperApi::$mockAdapter->getUsedReturns();
        $this->assertCount(4, $used_keys, 'Not all calls were made');

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    /**
     * Tests if deleting a tag works.
     *
     * @throws WordpressException
     * @throws \Throwable
     */
    public function testDeleteTag()
    {
        $this->initApiConnection();

        list($created_tag, $created_options) = $this->createTag();

        /*
         * Delete the tag
         */
        $this->mockApiCallsFromDirectory(self::DELETE_DATADUMP_DIRECTORY, true);
        $file = $this->getHookDataDump(self::DELETE_DATADUMP_HOOK);

        // Check the backref of the tag
        $backref = $file->getEventBackref();
        list($main_type, $options) = StoreKeeperApi::extractMainTypeAndOptions($backref);
        $this->assertEquals('BlogModule::Category', $main_type, 'Event type');

        $rest = $this->getRestWithToken($file);
        $response = $this->handleRequest($rest);
        $response = $response->get_data();
        $this->assertTrue($response['success'], 'Request failed');

        $this->runner->execute(ProcessAllTasks::getCommandName());
        $tag = $this->getTagByStoreKeeperID($options['id']);

        // Check if the ids are the same
        $this->assertEquals($options['id'], $created_options['id'], 'Updated tags have different ids');

        // Check if the deleted tag is null
        $this->assertFalse($tag, 'Deleted tag was not null');

        // Check if the deleted tag is not the same
        $this->assertNotEquals($created_tag, $tag, 'The deleted tag was the same as the old tag');

        // Make sure 3 requests have been made
        $used_keys = StoreKeeperApi::$mockAdapter->getUsedReturns();
        $this->assertCount(3, $used_keys, 'Not all calls were made');

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testOrderOnlySyncMode()
    {
        $this->initApiConnection();

        $this->assertTagCount(0, 'Environment is expected to be empty');

        StoreKeeperOptions::set(StoreKeeperOptions::SYNC_MODE, StoreKeeperOptions::SYNC_MODE_ORDER_ONLY);

        $this->createTag();

        $this->assertTagCount(0, 'No categories should be created');

        $this->updateTag();

        $used_keys = StoreKeeperApi::$mockAdapter->getUsedReturns();
        $this->assertCount(0, $used_keys, 'The api should not have been called');

        $this->assertTagCount(0, 'No categories should be updated');
    }

    private function assertTagCount(int $expected, string $message)
    {
        $arguments = [
            'taxonomy' => 'product_tag',
            'orderby' => 'ID',
            'order' => 'ASC',
            'hide_empty' => false,
        ];
        $this->assertCount($expected, get_tags($arguments), $message);
    }

    public function updateChanged($tag, $original_tag, $original_updated)
    {
        // Check if there was at least 1 key updated
        $totalChanged = 0;
        if ($tag->get('name') != $original_tag->get('name')) {
            $this->assertEquals($tag->get('name'), $original_updated->get('title'));
            ++$totalChanged;
        }
        if ($tag->get('slug') != $original_tag->get('slug')) {
            $this->assertEquals($tag->get('slug'), $original_updated->get('slug'));
            ++$totalChanged;
        }
        if ($tag->get('description') != $original_tag->get('description')) {
            $this->assertEquals($tag->get('description'), $original_updated->get('description'));
            ++$totalChanged;
        }

        $this->assertNotEquals(0, $totalChanged, 'Updated tags dont have at least 1 changed key');

        return $totalChanged;
    }

    /**
     * @param $storekeeper_id
     *
     * @return array|bool|\WP_Error|\WP_Term|null
     *
     * @throws WordpressException
     */
    protected function getTagByStoreKeeperID($storekeeper_id)
    {
        $tags = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => 'product_tag',
                    'slug' => '',
                    'hide_empty' => false,
                    'number' => 1,
                    'meta_query' => [
                        [
                            'key' => 'storekeeper_id',
                            'value' => $storekeeper_id,
                            'compare' => '=',
                        ],
                    ],
                ]
            )
        );

        if (1 === count($tags)) {
            return get_term(
                array_shift($tags),
                'product_tag'
            );
        }

        return false;
    }

    protected function createTag(): array
    {
        $this->mockApiCallsFromDirectory(self::CREATE_DATADUMP_DIRECTORY, true);
        $file = $this->getHookDataDump(self::CREATE_DATADUMP_HOOK);

        // Check is the tag is created
        $backref = $file->getEventBackref();
        list($main_type, $created_options) = StoreKeeperApi::extractMainTypeAndOptions($backref);
        $this->assertEquals('BlogModule::Category', $main_type, 'Event type');

        $rest = $this->getRestWithToken($file);
        $this->assertEquals('events', $file->getHookAction());

        $response = $this->handleRequest($rest);
        $response = $response->get_data();
        $this->assertTrue($response['success'], 'Request failed');

        // process the tasks
        $this->runner->execute(ProcessAllTasks::getCommandName());

        // Check if the tag was created
        $created_tag = new Dot($this->getTagByStoreKeeperID($created_options['id']));
        $this->assertNotFalse($created_tag, 'Tag was not created');

        return [$created_tag, $created_options];
    }

    protected function updateTag(): array
    {
        $this->mockApiCallsFromDirectory(self::UPDATE_DATADUMP_DIRECTORY, true);
        $file = $this->getHookDataDump(self::UPDATE_DATADUMP_HOOK);

        // Check the backref of the tag
        $backref = $file->getEventBackref();
        list($main_type, $updated_options) = StoreKeeperApi::extractMainTypeAndOptions($backref);
        $this->assertEquals('BlogModule::Category', $main_type, 'Event type');

        $rest = $this->getRestWithToken($file);
        $response = $this->handleRequest($rest);
        $response = $response->get_data();
        $this->assertTrue($response['success'], 'Request failed');

        $this->runner->execute(ProcessAllTasks::getCommandName());

        // Check if the tag was even updated(aka they should not be the same)
        $updated_tag = new Dot($this->getTagByStoreKeeperID($updated_options['id']));

        $file = $this->getDataDump(self::UPDATE_DATADUMP_DIRECTORY.'/'.self::UPDATE_DATADUMP_SOURCE_FILE);
        $original_updated = new Dot($file->getReturn()['data'][0]);

        return [$updated_tag, $updated_options, $original_updated];
    }
}
