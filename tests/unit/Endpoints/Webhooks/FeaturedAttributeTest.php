<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\UnitTest\AbstractProductTest;

class FeaturedAttributeTest extends AbstractProductTest
{
    const CREATE_DATADUMP_HOOK = 'events/hook.events.activateFeaturedAttribute.json';

    const FEATURED_ATTRIBUTE_PREFIX = 'storekeeper-woocommerce-b2c_featured_attribute_id-';

    public function testCreateFeaturedAttribute()
    {
        // Check if there are no featured attributes set before calling the hook
        $featured_attribute_keys = $this->fetchFeaturedAttributeOptionKeys();
        $this->assertEquals(
            0,
            count($featured_attribute_keys),
            'Test was not ran in an empty environment'
        );

        // Process the hook call and retrieve the details
        list($original_options, $original_details) = $this->handleHookRequest(
            self::CREATE_DATADUMP_HOOK,
            'ProductsModule::FeaturedAttribute',
            'events'
        );
        $original_options = new Dot($original_options);
        $original_details = new Dot($original_details);

        // Check if there is exactly one featured attribute set
        $featured_attribute_keys = $this->fetchFeaturedAttributeOptionKeys();
        $this->assertEquals(
            1,
            count($featured_attribute_keys),
            'Did not find exactly one featured attribute key within WooCommerce'
        );

        // Assert the featured attribute is correct in WooCommerce
        $this->assertFeaturedAttribute(
            $original_options->get('alias'),
            $original_details->get('attribute_id')
        );
    }

    public function testOrderOnlySyncMode()
    {
        StoreKeeperOptions::set(StoreKeeperOptions::SYNC_MODE, StoreKeeperOptions::SYNC_MODE_ORDER_ONLY);

        $this->assertFeaturedAttributes(0, 'Test was not ran on an empty environment');

        $this->handleHookRequest(
            self::CREATE_DATADUMP_HOOK,
            'ProductsModule::FeaturedAttribute',
            'events',
            false
        );

        $this->assertTaskCount(0, 'No tasks are supposed to be created');

        $this->runner->execute(ProcessAllTasks::getCommandName());

        $this->assertFeaturedAttributes(0, 'Featured attributes should not been imported');
    }

    private function assertFeaturedAttributes(int $expected, string $message)
    {
        $this->assertCount(
            $expected,
            $this->fetchFeaturedAttributeOptionKeys(),
            $message
        );
    }

    protected function handleHookRequest(
        $datadump_file,
        $expected_backref,
        $expected_hook_action,
        bool $process_tasks = true
    ): array {
        // Initialize the connection with the API
        $this->initApiConnection();

        // Setup the data dump
        $file = $this->getHookDataDump($datadump_file);

        // Check the backref of the product event
        $backref = $file->getEventBackref();
        list($main_type, $original_options) = StoreKeeperApi::extractMainTypeAndOptions($backref);
        $this->assertEquals(
            $expected_backref,
            $main_type,
            'WooCommerce event backref doesn\'t match the expected event backref'
        );

        // Check the hook action from the file
        $this->assertEquals(
            $expected_hook_action,
            $file->getHookAction(),
            'WooCommerce hook action doesn\'t match the expected hook action'
        );

        // Handle the request
        $rest = $this->getRestWithToken($file);
        $response = $this->handleRequest($rest);
        $response = $response->get_data();
        $this->assertTrue($response['success'], 'Request failed');

        if ($process_tasks) {
            $this->runner->execute(ProcessAllTasks::getCommandName());
        }

        // get the attribute details from the only event
        $original_details = array_pop($file->getBody()['payload']['events'])['details'];

        return [$original_options, $original_details];
    }

    protected function fetchFeaturedAttributeOptionKeys()
    {
        $matches = preg_grep(
            '/'.self::FEATURED_ATTRIBUTE_PREFIX.'.*/',
            array_keys(wp_load_alloptions())
        );

        return $matches;
    }

    protected function assertFeaturedAttribute($alias, $attribute_id)
    {
        // Fetch the featured attribute from WooCommerce
        $wc_featured_attribute = get_option(self::FEATURED_ATTRIBUTE_PREFIX.$alias);
        $this->assertNotFalse($wc_featured_attribute); // get_option will return false when no option is found
        $wc_featured_attribute = new Dot($wc_featured_attribute);

        // StoreKeeper id
        $expected_id = $attribute_id;
        $this->assertEquals(
            $expected_id,
            $wc_featured_attribute->get('attribute_id'),
            'WooCommerce id doesn\'t match the expected id'
        );

        // Name
        $expected_name = $alias;
        $this->assertEquals(
            $expected_name,
            $wc_featured_attribute->get('attribute_name'),
            'WooCommerce name doesn\'t match the expected name'
        );
    }
}
