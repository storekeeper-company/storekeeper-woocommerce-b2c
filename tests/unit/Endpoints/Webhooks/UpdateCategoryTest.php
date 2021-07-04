<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\TestLib\MediaHelper;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\CommandRunnerTrait;
use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;

class UpdateCategoryTest extends AbstractTest
{
    use CommandRunnerTrait;

    public function setUp()
    {
        parent::setUp();
        $this->setUpRunner();

        $this->mockMediaFromDirectory(self::UPDATE_DATADUMP_DIRECTORY.'/media');
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->tearDownRunner();
    }

    const CREATE_DATADUMP_DIRECTORY = 'events/createCategory';
    const CREATE_DATADUMP_HOOK = 'events/hook.events.createCategory.json';

    const DELETE_DATADUMP_DIRECTORY = 'events/deleteCategory';
    const DELETE_DATADUMP_HOOK = 'events/hook.events.deleteCategory.json';

    const UPDATE_DATADUMP_DIRECTORY = 'events/updateCategory';
    const UPDATE_DATADUMP_SOURCE_FILE = '20200326_110154.moduleFunction.ShopModule::listTranslatedCategoryForHooks.success.5e7c8ba1ea1bb.json';

    const MARKDOWN_PREFIX = '[sk_markdown]';
    const MARKDOWN_SUFFIX = '[/sk_markdown]';

    const UPLOADS_DIRECTORY = '/app/src/wp-content/uploads/';

    /**
     * Test if creating a category works.
     *
     * @throws \Throwable
     */
    public function testCreateCategory()
    {
        $this->initApiConnection();

        $this->createCategory();

        $used_keys = StoreKeeperApi::$mockAdapter->getUsedReturns();
        $this->assertCount(2, $used_keys, 'Not all calls were made');

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testOrderOnlySyncMode()
    {
        $this->initApiConnection();

        $this->assertCategoryCount(0, 'Environment is expected to be empty');

        StoreKeeperOptions::set(StoreKeeperOptions::SYNC_MODE, StoreKeeperOptions::SYNC_MODE_ORDER_ONLY);

        $this->createCategory();

        $this->assertCategoryCount(0, 'No categories should be created');

        $this->updateCategory();

        $used_keys = StoreKeeperApi::$mockAdapter->getUsedReturns();
        $this->assertCount(0, $used_keys, 'The api should not have been called');

        $this->assertCategoryCount(0, 'No categories should be updated');

        $this->assertTaskCount(0, 'No tasks are supposed to be created');
    }

    private function assertCategoryCount(int $expected, string $message)
    {
        $arguments = [
            'taxonomy' => 'product_cat',
            'orderby' => 'ID',
            'order' => 'ASC',
            'hide_empty' => false,
        ];
        $this->assertCount($expected, get_categories($arguments), $message);
    }

    /**
     * Test if updating a category works.
     *
     * @throws \Throwable
     */
    public function testUpdateCategory()
    {
        $this->initApiConnection();

        list($created_options) = $this->createCategory();
        list($updated_options, $updated_category) = $this->updateCategory();

        /*
         * Checking data
         */

        // Check if the ids are the same
        $this->assertEquals($updated_options['id'], $created_options['id'], 'Updated categories have different ids');

        $wc_category = $this->getCategoryByStoreKeeperID($updated_options['id']);

        // Get the WooCommerce category by the StoreKeeper ID. This also checks whether the ID is set correctly
        $this->assertNotFalse(
            $wc_category,
            'No WooCommerce category is set with StoreKeeper id '.$updated_category->get('id')
        );

        // Get the WooCommerce category meta data using the term_id of the retrieved category
        $wc_category_meta = get_term_meta($wc_category->term_id);
        $this->assertNotEmpty(
            $wc_category_meta,
            'No WooCommerce term metadata could be retrieved for the created term'
        );
        $wc_category_meta = new Dot($wc_category_meta);

        // Title
        $expected_title = $updated_category->get('title');
        $this->assertEquals(
            $expected_title,
            $wc_category->name,
            'WooCommerce title doesn\'t match the expected title'
        );

        // Slug
        $expected_slug = $updated_category->get('slug');
        $this->assertEquals(
            $expected_slug,
            $wc_category->slug,
            'WooCommerce slug doesn\'t match the expected slug'
        );

        // Summary
        $expected_summary = $updated_category->get('summary');
        if (!empty($expected_summary)) {
            $expected_summary = self::MARKDOWN_PREFIX.$expected_summary.self::MARKDOWN_SUFFIX;
        }
        $this->assertEquals(
            $expected_summary,
            $wc_category_meta->get('category_summary')[0],
            'WooCommerce summary doesn\'t match the expected summary'
        );

        // Description
        $expected_description = $updated_category->get('description');
        if (!empty($expected_description)) {
            $expected_description = self::MARKDOWN_PREFIX.$expected_description.self::MARKDOWN_SUFFIX;
        }
        $this->assertEquals(
            $expected_description,
            $wc_category_meta->get('category_description')[0],
            'WooCommerce description doesn\'t match the expected description'
        );

        // Parent category
        // When the level equals 1, there are no parent category except for the 'general' category from back-end
        if ($updated_category->get('category_tree.level') > 1 && $updated_category->has('parent_id')) {
            $expected_parent_storekeeper_id = $updated_category->get('parent_id');
            $wc_parent_category_meta = new Dot(get_term_meta($wc_category->parent));
            $this->assertEquals(
                $expected_parent_storekeeper_id,
                $wc_parent_category_meta->get('storekeeper_id')[0],
                'WooCommerce parent\'s StoreKeeper id doesn\'t match the expected parents StoreKeeper id'
            );
        }

        // Thumbnail image
        $expected_image_file = basename(parse_url($updated_category->get('image_url'))['path']);
        $wc_image_file = basename(wp_get_attachment_url($wc_category_meta->get('thumbnail_id')[0]));
        $this->assertEquals(
            $expected_image_file,
            $wc_image_file,
            'WooCommerce thumbnail image filename doesn\'t match the expected filename'
        );

        // Compare MD5 hash when an image is set
        if (!empty($updated_category->get('image_url'))) {
            $expected_image_file_md5 = md5_file(
                MediaHelper::getMediaPath(
                    $updated_category->get('image_url')
                )
            );
            $wc_image_file_md5 = md5_file(
                self::UPLOADS_DIRECTORY.wp_get_attachment_metadata(
                    $wc_category_meta->get('thumbnail_id')[0]
                )['file']
            );
            $this->assertEquals(
                $expected_image_file_md5,
                $wc_image_file_md5,
                'WooCommerce thumbnail image md5 doesn\'t match the expected image md5'
            );
        }

        // Make sure all calls were made (2 calls for create category, 2 calls for update category)
        $used_keys = StoreKeeperApi::$mockAdapter->getUsedReturns();
        $this->assertCount(4, $used_keys, 'Not all calls were made');

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    public function testDeleteCategory()
    {
        $this->initApiConnection();

        /*
         * Create original category
         */
        // First create a category to delete
        list($original_options, $original_category) = $this->createCategory();

        /*
         * Delete the category
         */
        $this->mockApiCallsFromDirectory(self::DELETE_DATADUMP_DIRECTORY, true);
        $file = $this->getHookDataDump(self::DELETE_DATADUMP_HOOK);

        // Check the backref of the category
        $backref = $file->getEventBackref();
        list($main_type, $options) = StoreKeeperApi::extractMainTypeAndOptions($backref);
        $this->assertEquals('BlogModule::Category', $main_type, 'Event type');

        $rest = $this->getRestWithToken($file);
        $response = $this->handleRequest($rest);
        $response = $response->get_data();
        $this->assertTrue($response['success'], 'Request failed');

        $this->runner->execute(ProcessAllTasks::getCommandName());
        $category = $this->getCategoryByStoreKeeperID($options['id']);

        /*
         * Checking data
         */
        // Check if the ids are the same
        $this->assertEquals($options['id'], $original_options['id'], 'Deleted categories have different ids');

        // Check if the deleted category is null
        $this->assertFalse($category, 'Deleted category was not null');

        // Check if the deleted category is not the same
        $this->assertNotEquals($original_category, $category, 'The deleted category was the same as the old category');

        // Make sure 3 requests have been made
        $used_keys = StoreKeeperApi::$mockAdapter->getUsedReturns();
        $this->assertCount(3, $used_keys, 'Not all calls were made');

        $this->assertTaskNotCount(0, 'There are suppose to be any tasks');
    }

    /**
     * @throws \Throwable
     */
    protected function updateCategory(): array
    {
        $this->mockApiCallsFromDirectory(self::UPDATE_DATADUMP_DIRECTORY, true);
        $file = $this->getHookDataDump('events/hook.events.updateCategory.json');

        // Check the backref of the category
        $backref = $file->getEventBackref();
        list($main_type, $updated_options) = StoreKeeperApi::extractMainTypeAndOptions($backref);
        $this->assertEquals('BlogModule::Category', $main_type, 'Event type');

        $rest = $this->getRestWithToken($file);
        $this->assertEquals('events', $file->getHookAction());

        $response = $this->handleRequest($rest);
        $response = $response->get_data();
        $this->assertTrue($response['success'], 'Request failed');

        $this->runner->execute(ProcessAllTasks::getCommandName());

        // Read the original data from the data dump
        $file = $this->getDataDump(self::UPDATE_DATADUMP_DIRECTORY.'/'.self::UPDATE_DATADUMP_SOURCE_FILE);
        $original_data = $file->getReturn()['data'][0];
        $updated_category = new Dot($original_data);

        return [$updated_options, $updated_category];
    }

    /**
     * @param $storekeeper_id
     *
     * @return array|bool|\WP_Error|\WP_Term|null
     *
     * @throws WordpressException
     */
    protected function getCategoryByStoreKeeperID($storekeeper_id)
    {
        $categories = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => 'product_cat',
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

        if (1 === count($categories)) {
            return get_term(
                array_shift($categories),
                'product_cat'
            );
        }

        return false;
    }

    /**
     * @throws WordpressException
     * @throws \Throwable
     */
    protected function createCategory(): array
    {
        $this->mockApiCallsFromDirectory(self::CREATE_DATADUMP_DIRECTORY, true);
        $file = $this->getHookDataDump(self::CREATE_DATADUMP_HOOK);

        // Check the backref of the category
        $backref = $file->getEventBackref();
        list($main_type, $created_options) = StoreKeeperApi::extractMainTypeAndOptions($backref);
        $this->assertEquals('BlogModule::Category', $main_type, 'Event type');

        $rest = $this->getRestWithToken($file);
        $this->assertEquals('events', $file->getHookAction());

        $response = $this->handleRequest($rest);
        $response = $response->get_data();
        $this->assertTrue($response['success'], 'Request failed');

        $this->runner->execute(ProcessAllTasks::getCommandName());
        $created_category = $this->getCategoryByStoreKeeperID($created_options['id']);
        $this->assertNotNull($created_category, 'Original category was null');

        return [$created_options, $created_category];
    }
}
