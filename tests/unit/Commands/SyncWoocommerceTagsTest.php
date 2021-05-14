<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceTags;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class SyncWoocommerceTagsTest extends AbstractTest
{
    // https://github.com/testdouble/contributing-tests/wiki/Arrange-Act-Assert

    // Datadump related constants
    const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-tags';
    const DATADUMP_SOURCE_FILE = 'moduleFunction.ShopModule::listTranslatedCategoryForHooks.aff56e222ac75ec857627f848c9fc4906ecd4dba5cd0007e180b7c1e9efc955c.json';

    public function testRun()
    {
        /*
         * Arrange
         */

        // Initialize the test
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY, true);

        // Read the original data from the data dump
        $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.self::DATADUMP_SOURCE_FILE);
        $original_tag_data = $file->getReturn()['data'];

        $default_tag_args = [
            'orderby' => 'name',
            'order' => 'asc',
            'hide_empty' => false,
        ];

        // Test whether there are no tags before import
        $product_tags = get_terms('product_tag', $default_tag_args);
        $this->assertEquals(
            0,
            count($product_tags),
            'Test was not ran in an empty environment'
        );

        /*
         * Act
         */

        // Run the tag import command
        $this->runner->execute(SyncWoocommerceTags::getCommandName());

        /*
         * Assert
         */

        // Retrieve all synchronised tags
        $product_tags = get_terms('product_tag', $default_tag_args);
        $this->assertEquals(
            count($original_tag_data),
            count($product_tags),
            'Amount of synchronised tags doesn\'t match source data'
        );

        foreach ($original_tag_data as $tag_data) {
            $original = new Dot($tag_data);

            // Get the WooCommerce tag by the StoreKeeper ID. This also checks whether the ID is set correctly
            $wc_tag = $this->getTagByStoreKeeperID($original->get('id'));
            $this->assertNotFalse($wc_tag, 'No WooCommerce tag is set with StoreKeeper id '.$original->get('id'));

            // Title
            $expected_title = $original->get('title');
            $this->assertEquals(
                $expected_title,
                $wc_tag->name,
                'WooCommerce tag title doesn\'t match the expected tag title'
            );
            unset($expected_title);

            // Slug
            $expected_slug = $original->get('slug');
            $this->assertEquals(
                $expected_slug,
                $wc_tag->slug,
                'WooCommerce tag slug doesn\'t match the expected tag slug'
            );
            unset($expected_slug);

            unset($original);
            unset($wc_tag);
        }
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
}
