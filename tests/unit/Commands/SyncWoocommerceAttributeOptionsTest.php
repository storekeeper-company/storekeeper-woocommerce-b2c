<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;
use StoreKeeper\WooCommerce\B2C\Tools\Media;

class SyncWoocommerceAttributeOptionsTest extends AbstractTest
{
    // Datadump related constants
    const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-attribute-options';
    const DATADUMP_SOURCE_FILE = '20200326_120239.moduleFunction.BlogModule::listTranslatedAttributeOptions.success.5e7c99df99430.json';

    public function testRun()
    {
        /*
         * Arrange
         */

        // Initialize the test
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY, true);
        $this->mockMediaFromDirectory(self::DATADUMP_DIRECTORY.'/media');

        // Read the original data from the data dump
        $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.self::DATADUMP_SOURCE_FILE);
        $return = $file->getReturn();
        $original_options = $return['data'];

        // Gets the amount attribute options from the dump
        $count = $file->getReturn()['count'];

        // Test whether there are no attribute options before import
        $all_attribute_options = $this->fetchAllStoreKeeperAttributeOptions();
        $this->assertEquals(
            0,
            count($all_attribute_options),
            'Test was not ran in an empty environment'
        );

        /*
         * Act
         */

        // Run the tag import command
        $this->runner->execute(SyncWoocommerceAttributeOptions::getCommandName());

        /*
         * Assert
         */

        // Retrieve all synchronised tags
        foreach ($original_options as $original_option_data) {
            $original_option = new Dot($original_option_data);
            $name = "{$original_option->get('attribute.name')}::{$original_option->get('name')}";
            $term_id = Attributes::getAttributeOptionTermIdByAttributeOptionId(
                $original_option->get('id'),
                $original_option->get('attribute.name')
            );

            $this->assertNotEmpty(
                $term_id,
                "Attribute option \"$name\" is found"
            );

            $term = get_term($term_id);
            $this->assertNotInstanceOf(
                'WP_Error',
                $term,
                "Attribute option \"$name\" is imported"
            );

            $this->assertEquals(
                $original_option->get('label'),
                $term->name,
                "Attribute option name for \"$name\" is correct"
            );

            $expected_slug = Attributes::sanitizeOptionSlug($original_option->get('id'), $original_option->get('name'));
            $this->assertEquals(
                $expected_slug,
                $term->slug,
                "Attribute option slug for \"$name\" is correct"
            );

            $storekeeper_id = get_term_meta($term_id, 'storekeeper_id', true);
            $this->assertEquals(
                $original_option->get('id'),
                $storekeeper_id,
                "Attribute option id for \"$name\" is imported"
            );

            if ($original_option->has('image_url')) {
                // Check media
                $current_media_id = get_term_meta($term_id, 'product_attribute_image', true);
                $media_id = Media::getAttachmentId($original_option->get('image_url'));
                $this->assertEquals(
                    $current_media_id,
                    $media_id,
                    "Attribute option image for \"$name\" is imported"
                );

                $original_url = $original_option->get('image_url');
                $current_url = get_post_meta($current_media_id, 'original_url', true);

                $this->assertFileUrls(Media::fixUrl($original_url), Media::fixUrl($current_url));
            } else {
                $this->assertNull(
                    $original_option->get('image_url'),
                    "No Attribute option image for \"$name\""
                );
            }

            $all_attribute_options = $this->fetchAllStoreKeeperAttributeOptions();
            $this->assertEquals(
                $count,
                count($all_attribute_options),
                'More/less than 1 attribute option was created.'
            );
        }
    }

    public function fetchAllStoreKeeperAttributeOptions()
    {
        $sql = <<<SQL
            SELECT * 
            FROM `wp_terms`
            WHERE `slug` LIKE 'sk_%'
SQL;

        return $this->db->querySql($sql)->fetch_all();
    }
}
