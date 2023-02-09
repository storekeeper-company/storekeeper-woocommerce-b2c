<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceCategories;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\StorekeeperSeo;
use StoreKeeper\WooCommerce\B2C\TestLib\MediaHelper;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class SyncWoocommerceCategoriesTest extends AbstractTest
{
    // https://github.com/testdouble/contributing-tests/wiki/Arrange-Act-Assert

    // Datadump related constants
    const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-categories';
    const DATADUMP_SOURCE_FILE = 'moduleFunction.ShopModule::listTranslatedCategoryForHooks.success.5e79a1f00b651.json';

    const MARKDOWN_PREFIX = '[sk_markdown]';
    const MARKDOWN_SUFFIX = '[/sk_markdown]';

    const UPLOADS_DIRECTORY = '/app/src/wp-content/uploads/';

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
        $original_categories_data = $file->getReturn()['data'];

        $default_category_args = [
            'orderby' => 'name',
            'order' => 'asc',
            'hide_empty' => false,
        ];

        // Test whether there are no categories before import
        $product_categories = get_terms('product_cat', $default_category_args);
        try {
            if (is_wp_error($product_categories)) {
                $product_categories = [];
            }
        } catch (\Throwable $throwable) {
            $product_categories = [];
        }

        if (!is_wp_error($product_categories)) {
            $this->assertCount(
                0,
                $product_categories,
                'Test was not ran in an empty environment'
            );
        }

        /*
         * Act
         */

        // Run the category import command
        $this->runner->execute(SyncWoocommerceCategories::getCommandName());

        $termMeta = WordpressExceptionThrower::throwExceptionOnWpError(
            get_option(StorekeeperSeo::STOREKEEPER_SEO_OPTION_KEY)
        );
        $this->assertEquals(
            count($termMeta['product_cat']),
            1,
            'Only one category should have synchronized SEO data'
        );

        /*
         * Assert
         */

        // Retrieve all synchronised categories
        $product_categories = get_terms('product_cat', $default_category_args);
        $this->assertEquals(
            count($original_categories_data),
            count($product_categories),
            'Amount of synchronised categories doesn\'t match source data'
        );

        foreach ($original_categories_data as $category_data) {
            $original = new Dot($category_data);

            foreach ($termMeta['product_cat'] as $categoryMeta) {
                if ($categoryMeta['category_id'] == $category_data['id']) {
                    if (isset($category_data['seo_title'])) {
                        $this->assertEquals($category_data['seo_title'], $categoryMeta['skseo_title']);
                    }
                    if (isset($category_data['seo_keywords'])) {
                        $this->assertEquals($category_data['seo_keywords'], $categoryMeta['skseo_desc']);
                    }
                    if (isset($category_data['seo_description'])) {
                        $this->assertEquals($category_data['seo_description'], $categoryMeta['skseo_focuskw']);
                    }
                }
            }

            // Get the WooCommerce category by the StoreKeeper ID. This also checks whether the ID is set correctly
            $wc_category = $this->getCategoryByStoreKeeperID($original->get('id'));
            $this->assertNotFalse(
                $wc_category,
                'No WooCommerce category is set with StoreKeeper id '.$original->get('id')
            );

            // Get the WooCommerce category meta data using the term_id of the retrieved category
            $wc_category_meta = get_term_meta($wc_category->term_id);
            $this->assertNotEmpty(
                $wc_category_meta,
                'No WooCommerce term metadata could be retrieved for the created term'
            );
            $wc_category_meta = new Dot($wc_category_meta);

            // Title
            $expected_title = $original->get('title');
            $this->assertEquals(
                $expected_title,
                $wc_category->name,
                'WooCommerce title doesn\'t match the expected title'
            );
            unset($expected_title);

            // Slug
            $expected_slug = $original->get('slug');
            $this->assertEquals(
                $expected_slug,
                $wc_category->slug,
                'WooCommerce slug doesn\'t match the expected slug'
            );
            unset($expected_slug);

            // Summary
            $expected_summary = $original->get('summary');
            if (!empty($expected_summary)) {
                $expected_summary = self::MARKDOWN_PREFIX.$expected_summary.self::MARKDOWN_SUFFIX;
            }
            $this->assertEquals(
                $expected_summary,
                $wc_category_meta->get('category_summary')[0],
                'WooCommerce summary doesn\'t match the expected summary'
            );
            unset($expected_summary);

            // Description
            $expected_description = $original->get('description');
            if (!empty($expected_description)) {
                $expected_description = self::MARKDOWN_PREFIX.$expected_description.self::MARKDOWN_SUFFIX;
            }
            $this->assertEquals(
                $expected_description,
                $wc_category_meta->get('category_description')[0],
                'WooCommerce description doesn\'t match the expected description'
            );
            unset($expected_description);

            // Parent category
            // When the level equals 1, there are no parent categorie except for the 'general' category from back-end
            if ($original->get('category_tree.level') > 1 && $original->has('parent_id')) {
                $expected_parent_storekeeper_id = $original->get('parent_id');
                $wc_parent_category_meta = new Dot(get_term_meta($wc_category->parent));
                $this->assertEquals(
                    $expected_parent_storekeeper_id,
                    $wc_parent_category_meta->get('storekeeper_id')[0],
                    'WooCommerce parent\'s StoreKeeper id doesn\'t match the expected parents StoreKeeper id'
                );
                unset($expected_parent_storekeeper_id);
                unset($wc_parent_category_meta);
            }

            // Thumbnail image
            $expected_image_file = basename(parse_url($original->get('image_url'))['path']);
            $wc_image_file = basename(wp_get_attachment_url($wc_category_meta->get('thumbnail_id')[0]));
            $this->assertEquals(
                $expected_image_file,
                $wc_image_file,
                'WooCommerce thumbnail image filename doesn\'t match the expected filename'
            );
            unset($expected_image_file);
            unset($wc_image_file);

            // Compare MD5 hash when an image is set
            if (!empty($original->get('image_url'))) {
                $expected_image_file_md5 = md5_file(
                    MediaHelper::getMediaPath($original->get('image_url'))
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
                unset($expected_image_file_md5);
                unset($wc_image_file_md5);
            }

            // Unset retrieved objects to ensure clean data in the next iteration(s)
            unset($wc_category);
            unset($wc_category_meta);
            unset($original);
        }
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
}
