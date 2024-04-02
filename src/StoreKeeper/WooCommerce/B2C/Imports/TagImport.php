<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\WithConsoleProgressBarInterface;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use StoreKeeper\WooCommerce\B2C\Traits\ConsoleProgressBarTrait;

class TagImport extends AbstractImport implements WithConsoleProgressBarInterface
{
    use ConsoleProgressBarTrait;
    private $storekeeper_id = 0;
    public const WOOCOMMERCE_PRODUCT_TAG_TAXONOMY = 'product_tag';

    /**
     * CategoryImport constructor.
     *
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        $this->storekeeper_id = key_exists('storekeeper_id', $settings) ? (int) $settings['storekeeper_id'] : 0;
        unset($settings['storekeeper_id']);
        parent::__construct($settings);
    }

    protected function getModule()
    {
        return 'ShopModule';
    }

    protected function getFunction()
    {
        return 'listTranslatedCategoryForHooks';
    }

    protected function getFilters()
    {
        $f = [
            [
                'name' => 'category_type/alias__=',
                'val' => 'Label',
            ],
            [
                'name' => 'category_type/module_name__=',
                'val' => 'ProductsModule',
            ],
        ];

        if ($this->storekeeper_id > 0) {
            $f[] = [
                'name' => 'id__=',
                'val' => $this->storekeeper_id,
            ];
        }

        return $f;
    }

    /**
     * @return bool
     *
     * @throws WordpressException
     */
    private function getItem($StoreKeeperId)
    {
        $labels = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => self::WOOCOMMERCE_PRODUCT_TAG_TAXONOMY,
                    'hide_empty' => false,
                    'number' => 1,
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $StoreKeeperId,
                ]
            )
        );

        if (1 === count($labels)) {
            return $labels[0];
        }

        return false;
    }

    /**
     * @return bool
     *
     * @throws WordpressException
     */
    private function getItemBySlug($slug)
    {
        $labels = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => self::WOOCOMMERCE_PRODUCT_TAG_TAXONOMY,
                    'hide_empty' => false,
                    'number' => 1,
                    'meta_key' => 'slug',
                    'meta_value' => $slug,
                ]
            )
        );

        if (1 === count($labels)) {
            return $labels[0];
        }

        if (empty($labels)) {
            $productTag = WordpressExceptionThrower::throwExceptionOnWpError(
                get_term_by('slug', $slug, self::WOOCOMMERCE_PRODUCT_TAG_TAXONOMY)
            );

            if ($productTag) {
                return $productTag;
            }
        }

        return false;
    }

    /**
     * @param Dot $dotObject
     *
     * @throws \Exception
     * @throws WordpressException
     */
    protected function processItem($dotObject, array $options = []): ?int
    {
        $term = $this->getItem($dotObject->get('id'));

        if (false === $term) {
            $term = $this->getItemBySlug($dotObject->get('slug', null));
        }

        if (false !== $term) {
            // Check if the category is published
            if (!$dotObject->get('published')) {
                WordpressExceptionThrower::throwExceptionOnWpError(wp_delete_term($term->ID, self::WOOCOMMERCE_PRODUCT_TAG_TAXONOMY));

                return null;
            }

            $title = $this->getTranslationIfRequired($dotObject, 'title');

            if (empty(trim($title))) {
                return null;
            }

            // Update
            WordpressExceptionThrower::throwExceptionOnWpError(
                wp_update_term(
                    $term->term_id,
                    self::WOOCOMMERCE_PRODUCT_TAG_TAXONOMY,
                    [
                        'name' => $title,
                        'slug' => $dotObject->get('slug', null),
                    ]
                )
            );
        } else {
            $title = $this->getTranslationIfRequired($dotObject, 'title');

            if (empty(trim($title))) {
                return null;
            }

            $slug = $dotObject->get('slug', null);
            $slug = wp_unique_term_slug($slug, (object) [
                'taxonomy' => self::WOOCOMMERCE_PRODUCT_TAG_TAXONOMY,
            ]);

            // Create
            $term = WordpressExceptionThrower::throwExceptionOnWpError(
                wp_insert_term(
                    $title,
                    self::WOOCOMMERCE_PRODUCT_TAG_TAXONOMY,
                    [
                        'slug' => $slug,
                    ]
                )
            );
            $term_id = $term['term_id'];
            add_term_meta($term_id, 'storekeeper_id', $dotObject->get('id'), true);
        }

        return null;
    }

    protected function getImportEntityName(): string
    {
        return __('tags', I18N::DOMAIN);
    }
}
