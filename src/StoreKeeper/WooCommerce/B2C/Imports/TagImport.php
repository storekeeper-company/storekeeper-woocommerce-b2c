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
     * @param $StoreKeeperId
     *
     * @return bool
     *
     * @throws WordpressException
     */
    private function getItem($StoreKeeperId)
    {
        $labels = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => 'product_tag',
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
     * @param $slug
     *
     * @return bool
     *
     * @throws WordpressException
     */
    private function getItemBySlug($slug)
    {
        $labels = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => 'product_tag',
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

        return false;
    }

    /**
     * @param Dot $dotObject
     *
     * @throws \Exception
     * @throws WordpressException
     */
    protected function processItem($dotObject, array $options = [])
    {
        $term = $this->getItem($dotObject->get('id'));

        if (false === $term) {
            $term = $this->getItemBySlug($dotObject->get('slug', null));
        }

        if (false !== $term) {
            // Check if the category is published
            if (!$dotObject->get('published')) {
                WordpressExceptionThrower::throwExceptionOnWpError(wp_delete_term($term->ID, 'product_tag'));

                return;
            }

            $title = $this->getTranslationIfRequired($dotObject, 'title');

            if (empty(trim($title))) {
                return;  // todo wtf?
            }

            // Update
            WordpressExceptionThrower::throwExceptionOnWpError(
                wp_update_term(
                    $term->term_id,
                    'product_tag',
                    [
                        'name' => $title,
                        'slug' => $dotObject->get('slug', null),
                    ]
                )
            );
        } else {
            $title = $this->getTranslationIfRequired($dotObject, 'title');

            if (empty(trim($title))) {
                return;  // todo wtf?
            }

            // Create
            $term = WordpressExceptionThrower::throwExceptionOnWpError(
                wp_insert_term(
                    $title,
                    'product_tag',
                    [
                        'slug' => $dotObject->get('slug', null),
                    ]
                )
            );
            $term_id = $term['term_id'];
            add_term_meta($term_id, 'storekeeper_id', $dotObject->get('id'), true);
        }
    }

    protected function getImportEntityName(): string
    {
        return __('tags', I18N::DOMAIN);
    }
}
