<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceAttributes;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;

class SyncWoocommerceAttributesTest extends AbstractTest
{
    /*
     * Attribute creation documentation. This is implemented in StoreKeeper/WooCommerce/B2C/Imports/AttributeImport.php
     * https://docs.woocommerce.com/wc-apidocs/function-wc_create_attribute.html
     *
     * Fetching a list of attributes from WooCommerce
     * https://docs.woocommerce.com/wc-apidocs/function-wc_get_attribute_taxonomies.html
     */

    // Datadump related constants
    const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-attributes';
    const DATADUMP_SOURCE_FILE = 'moduleFunction.BlogModule::listTranslatedAttributes.633cdd60f6610605a1bbef88a9c0415dc5576d8177a3e73793ebbaf9f7fd6342.json';
    const DATADUMP_RESERVED_DIRECTORY = 'commands/sync-woocommerce-reserved-attributes';
    const DATADUMP_RESERVED_SOURCE_FILE = 'moduleFunction.BlogModule::listTranslatedAttributes.reserved.json';

    const MAX_LENGTH_ATTRIBUTE_LABEL = 30;

    public function testInit()
    {
        // Initialize the test
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY, true);

        // Read the original data from the data dump
        $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.self::DATADUMP_SOURCE_FILE);
        $original_attribute_data = $file->getReturn()['data'];

        // Check if no attributes are there before running the commnand
        $product_attributes = wc_get_attribute_taxonomies();
        $this->assertEquals(
            0,
            count($product_attributes),
            'Test was not ran in an empty environment'
        );

        // Run the attribute import command
        $this->runner->execute(SyncWoocommerceAttributes::getCommandName());

        // Check if the amount of attributes matches the amount from the data dump
        $product_attributes = wc_get_attribute_taxonomies();
        $this->assertEquals(
            count($original_attribute_data),
            count($product_attributes),
            'Amount of synchronised attributes doesn\'t match source data'
        );

        foreach ($original_attribute_data as $attribute_data) {
            $original = new Dot($attribute_data);

            // Fetch the Woocommerce attribute based on the slug
            $wc_attribute = $this->fetchWCAttributeBySlug($original->get('name'));
            $this->assertNotFalse($wc_attribute, 'No WooCommerce attribute is set with this slug');

            // StoreKeeper id
            $storekeeper_id = $this->fetchAttributeStoreKeeperId($wc_attribute->attribute_id);
            $this->assertEquals(
                $original->get('id'),
                $storekeeper_id->meta_value,
                'The Woocommerce Attribute doesn\'t have the correct StoreKeeper id'
            );

            // Attribute name
            $expected_attribute_name = $original->get('name');
            $this->assertEquals(
                $expected_attribute_name,
                $wc_attribute->attribute_name,
                'WooCommerce attribute name doesn\'t match the expected attribute name'
            );

            // Attribute label
            $expected_attribute_label = $original->get('label');
            // There is a maximum length of 30 characters. StoreKeeper/WooCommerce/B2C/Imports/AttributeImport.php:90
            if (strlen($expected_attribute_label) > self::MAX_LENGTH_ATTRIBUTE_LABEL) {
                $expected_attribute_label = substr($expected_attribute_label, 0, self::MAX_LENGTH_ATTRIBUTE_LABEL);
            }
            $this->assertEquals(
                $expected_attribute_label,
                $wc_attribute->attribute_label,
                'WooCommerce attribute label doesn\'t match the expected attribute label'
            );

            // Attribute type
            // In case of a new attribute, this will always be the same (StoreKeeper/WooCommerce/B2C/Imports/AttributeImport.php:96)
            $expected_attribute_type = Attributes::getDefaultType();
            $this->assertEquals(
                $expected_attribute_type,
                $wc_attribute->attribute_type,
                'WooCommerce attribute type doesn\'t match the expected attribute type'
            );
        }
    }

    public function testReservedAttribute()
    {
        // Initialize the test
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_RESERVED_DIRECTORY, true);

        // Read the original data from the data dump
        $file = $this->getDataDump(self::DATADUMP_RESERVED_DIRECTORY.'/'.self::DATADUMP_RESERVED_SOURCE_FILE);
        $original_attribute_data = $file->getReturn()['data'];

        // Check if no attributes are there before running the commnand
        $product_attributes = wc_get_attribute_taxonomies();
        $this->assertCount(
            0, $product_attributes, 'Test was not ran in an empty environment'
        );

        $this->runner->execute(SyncWoocommerceAttributes::getCommandName());
        $attribute1 = $this->assertReservedAttributes($original_attribute_data, 'Run the attribute import command');

        $this->runner->execute(SyncWoocommerceAttributes::getCommandName());
        $attribute2 = $this->assertReservedAttributes($original_attribute_data, 'Run second time to check if attribute update fails');

        $this->assertEquals($attribute1->attribute_name, $attribute2->attribute_name, 'Attributes should be the same');
    }

    /**
     * Fetch the Woocommerce attribute object based on the slug.
     *
     * @param $slug
     *
     * @return bool|mixed
     */
    protected function fetchWCAttributeBySlug($slug)
    {
        $attribute_taxonomies = wc_get_attribute_taxonomies();

        foreach ($attribute_taxonomies as $attribute_taxonomy) {
            if ($attribute_taxonomy->attribute_name === $slug) {
                return $attribute_taxonomy;
            }
        }

        return false;
    }

    /**
     * Retrieve the StoreKeeper id of the attribute from the database. Since this is a custom table, it's not retrievable
     * using a native Wordpress / Woocommerce function.
     *
     * @param $attribute_id
     *
     * @return array|object|void|null
     */
    protected function fetchAttributeStoreKeeperId($attribute_id)
    {
        if (!is_null(wc_get_attribute($attribute_id))) {
            $table_name = 'wp_storekeeper_woocommerce_attribute_metadata';
            $sql = <<<SQL
            SELECT `meta_value` 
            FROM `$table_name`
            WHERE `attribute_id` = %d
            AND `meta_key` = 'storekeeper_id'
SQL;

            // Use wpdb to prepare the SQL statement
            global $wpdb;
            $sqlPrepared = $wpdb->prepare($sql, $attribute_id);

            return (object) $this->db->querySql($sqlPrepared)->fetch_assoc();
        }

        return null;
    }

    /**
     * @param $original_attribute_data
     */
    public function assertReservedAttributes($original_attribute_data, string $message): object
    {
        // Check if the amount of attributes matches the amount from the data dump
        $product_attributes = wc_get_attribute_taxonomies();
        $this->assertSameSize(
            $original_attribute_data,
            $product_attributes,
            $message.': Amount of synchronised attributes doesn\'t match source data'
        );

        $original = new Dot($original_attribute_data[0]);
        $wc_attribute = $product_attributes[array_key_first($product_attributes)];

        // StoreKeeper id
        $storekeeper_id = $this->fetchAttributeStoreKeeperId($wc_attribute->attribute_id);
        $this->assertEquals(
            $original->get('id'),
            $storekeeper_id->meta_value,
            $message.': The Woocommerce Attribute doesn\'t have the correct StoreKeeper id'
        );

        // Attribute name
        $expected_attribute_name = $original->get('name');
        $this->assertStringContainsStringIgnoringCase(
            $expected_attribute_name,
            $wc_attribute->attribute_name,
            $message.': WooCommerce attribute name doesn\'t match the expected attribute name'
        );

        // Attribute label
        $expected_attribute_label = $original->get('label');
        $this->assertEquals(
            $expected_attribute_label,
            $wc_attribute->attribute_label,
            $message.': WooCommerce attribute label doesn\'t match the expected attribute label'
        );

        // Attribute type
        // In case of a new attribute, this will always be the same (StoreKeeper/WooCommerce/B2C/Imports/AttributeImport.php:96)
        $expected_attribute_type = Attributes::getDefaultType();
        $this->assertEquals(
            $expected_attribute_type,
            $wc_attribute->attribute_type,
            $message.': WooCommerce attribute type doesn\'t match the expected attribute type'
        );

        return $wc_attribute;
    }
}
