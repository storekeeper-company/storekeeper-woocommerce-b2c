<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceAttributes;
use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;
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

    const MAX_LENGTH_ATTRIBUTE_LABEL = Attributes::MAX_NAME_LENGTH;

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
        $this->assertCount(
            0,
            $product_attributes,
            'Test was not ran in an empty environment'
        );

        // Run the attribute import command
        $this->runner->execute(SyncWoocommerceAttributes::getCommandName());

        // Check if the amount of attributes matches the amount from the data dump
        $product_attributes = wc_get_attribute_taxonomies();
        $this->assertCount(
            count($original_attribute_data), $product_attributes, 'Amount of synchronised attributes doesn\'t match source data'
        );

        foreach ($original_attribute_data as $attribute_data) {
            $original = new Dot($attribute_data);
            $attributeName = $original->get('name');

            // There is a maximum length of 25 characters
            if (strlen($attributeName) > Attributes::TAXONOMY_MAX_LENGTH) {
                $attributeName = substr($attributeName, 0, Attributes::TAXONOMY_MAX_LENGTH);
            }

            // Fetch the Woocommerce attribute based on the slug
            $wc_attribute = $this->fetchWCAttributeBySlug($attributeName);
            $this->assertNotFalse($wc_attribute, 'No WooCommerce attribute is set with this slug');

            // StoreKeeper id
            $storekeeper_id = $this->fetchAttributeStoreKeeperId($wc_attribute->attribute_id);
            $this->assertEquals(
                $original->get('id'),
                $storekeeper_id,
                'The Woocommerce Attribute doesn\'t have the correct StoreKeeper id'
            );

            // Attribute name
            $expected_attribute_name = $attributeName;
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
            $this->assertEquals(
                Attributes::TYPE_DEFAULT,
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

    protected function fetchAttributeStoreKeeperId($attribute_id): ?int
    {
        return AttributeModel::getAttributeStoreKeeperId($attribute_id);
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
            $storekeeper_id,
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
        $this->assertEquals(
            Attributes::TYPE_DEFAULT,
            $wc_attribute->attribute_type,
            $message.': WooCommerce attribute type doesn\'t match the expected attribute type'
        );

        return $wc_attribute;
    }
}
