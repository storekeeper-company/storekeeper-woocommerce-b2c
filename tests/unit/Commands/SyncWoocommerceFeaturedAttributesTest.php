<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceFeaturedAttributes;

class SyncWoocommerceFeaturedAttributesTest extends AbstractTest
{
    // Datadump related constants
    const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-featured-attributes';
    const DATADUMP_SOURCE_FILE = 'moduleFunction.ProductsModule::listFeaturedAttributes.b27e839d4ee191490b58c0f5abe45f80d6dee9463f978f058c64c63c1cf8d437.json';

    const FEATURED_ATTRIBUTE_PREFIX = 'storekeeper-woocommerce-b2c_featured_attribute_id-';

    public function testInit()
    {
        // Initialize the test
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY, true);

        $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.self::DATADUMP_SOURCE_FILE);
        $original_featured_attribute_data = $file->getReturn()['data'];

        // Check if there are no featured attributes set before running the command
        $featured_attribute_keys = $this->fetchFeaturedAttributeOptionKeys();
        $this->assertEquals(
            0,
            count($featured_attribute_keys),
            'Test was not ran in an empty environment'
        );

        // Run the featured attribute import command
        $this->runner->execute(SyncWoocommerceFeaturedAttributes::getCommandName());

        // Check if the amount of featured attributes matches the amount from the data dump
        $featured_attribute_keys = $this->fetchFeaturedAttributeOptionKeys();
        $this->assertEquals(
            count($original_featured_attribute_data),
            count($featured_attribute_keys),
            'Amount of synchronised featured attributes doesn\'t match source data'
        );

        foreach ($original_featured_attribute_data as $featured_attribute_data) {
            $original = new Dot($featured_attribute_data);

            // Fetch the featured attribute from WooCommerce
            $wc_featured_attribute = get_option(self::FEATURED_ATTRIBUTE_PREFIX.$original->get('alias'));
            $this->assertNotFalse($wc_featured_attribute); // get_option will return false when no option is found

            $wc_featured_attribute = new Dot($wc_featured_attribute);

            // StoreKeeper id
            $expected_id = $original->get('attribute_id');
            $this->assertEquals(
                $expected_id,
                $wc_featured_attribute->get('attribute_id'),
                'WooCommerce id doesn\'t match the expected id'
            );

            // Name
            $expected_name = $original->get('alias');
            $this->assertEquals(
                $expected_name,
                $wc_featured_attribute->get('attribute_name'),
                'WooCommerce name doesn\'t match the expected name'
            );
        }
    }

    protected function fetchFeaturedAttributeOptionKeys()
    {
        $matches = preg_grep(
            '/'.self::FEATURED_ATTRIBUTE_PREFIX.'.*/',
            array_keys(wp_load_alloptions())
        );

        return $matches;
    }
}
