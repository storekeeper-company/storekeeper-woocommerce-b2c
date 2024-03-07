<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Frontend\ShortCodes\MarkdownCode;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\RankMathSeo;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\StoreKeeperSeo;
use StoreKeeper\WooCommerce\B2C\Helpers\Seo\YoastSeo;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\WithConsoleProgressBarInterface;
use StoreKeeper\WooCommerce\B2C\Objects\PluginStatus;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Categories;
use StoreKeeper\WooCommerce\B2C\Tools\Media;
use StoreKeeper\WooCommerce\B2C\Tools\ParseDown;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use StoreKeeper\WooCommerce\B2C\Traits\ConsoleProgressBarTrait;

class CategoryImport extends AbstractImport implements WithConsoleProgressBarInterface
{
    use ConsoleProgressBarTrait;

    /**
     * @var int|null
     */
    private $storekeeper_id = 0;

    /**
     * @var int|null
     */
    private $level;

    /**
     * @var bool
     */
    private $descriptionAsHtml;

    /**
     * CategoryImport constructor.
     *
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        $this->storekeeper_id = key_exists('storekeeper_id', $settings) ? (int) $settings['storekeeper_id'] : 0;
        $this->level = key_exists('level', $settings) ? (int) $settings['level'] : null;
        unset($settings['level']);
        unset($settings['storekeeper_id']);

        $name = StoreKeeperOptions::getConstant(StoreKeeperOptions::CATEGORY_DESCRIPTION_HTML);
        $this->descriptionAsHtml = StoreKeeperOptions::getBoolOption($name, false);

        if ($this->descriptionAsHtml) {
            $this->enableAllowHtmlInDescriptions();
        }
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
                'name' => 'parent_id__notnull',
                'val' => '1',
            ],
            [
                'name' => 'category_type/alias__=',
                'val' => 'Product',
            ],
            [
                'name' => 'category_type/module_name__=',
                'val' => 'ProductsModule',
            ],
        ];

        if (!is_null($this->level)) {
            $f[] = [
                'name' => 'category_tree/level__=',
                'val' => $this->level,
            ];
        }

        if ($this->storekeeper_id > 0) {
            $f[] = [
                'name' => 'id__=',
                'val' => $this->storekeeper_id,
            ];
        }

        if (StoreKeeperOptions::exists(StoreKeeperOptions::MAIN_CATEGORY_ID) && StoreKeeperOptions::get(
            StoreKeeperOptions::MAIN_CATEGORY_ID
        ) > 0) {
            $f[] = [
                'name' => 'category_tree/path__overlap',
                'multi_val' => [StoreKeeperOptions::get(StoreKeeperOptions::MAIN_CATEGORY_ID)],
            ];
        }

        return $f;
    }

    public function run(array $options = []): void
    {
        if (StoreKeeperOptions::exists(StoreKeeperOptions::MAIN_CATEGORY_ID) && StoreKeeperOptions::get(
            StoreKeeperOptions::MAIN_CATEGORY_ID
        ) > 0) {
            $this->importMainCategory(StoreKeeperOptions::get(StoreKeeperOptions::MAIN_CATEGORY_ID));
        }

        parent::run();
    }

    public function importMainCategory($id)
    {
        $this->debug('Importing main category by ID', $id);

        $moduleName = $this->getModule();
        $module = $this->storekeeper_api->getModule($moduleName);
        $functionName = $this->getFunction();
        $query = $this->getQuery();
        $language = $this->getLanguage();
        $filters = [
            [
                'name' => 'id__=',
                'val' => $id,
            ],
        ];
        $sorts = $this->getSorts();
        $start = 0;
        $limit = 1;

        $this->debug('updates data', $filters);

        $this->debug("Stared $moduleName::$functionName");

        $response = [];
        if (is_null($query) && is_null($language)) {
            $response = $module->$functionName($start, $limit, $sorts, $filters);
        } else {
            if (is_null($query) && !is_null($language)) {
                $response = $module->$functionName($language, $start, $limit, $sorts, $filters);
            } else {
                if (!is_null($query) && !is_null($language)) {
                    $response = $module->$functionName($query, $language, $start, $limit, $sorts, $filters);
                } else {
                    if (!is_null($query) && is_null($language)) {
                        $response = $module->$functionName($query, $start, $limit, $sorts, $filters);
                    }
                }
            }
        }

        if (key_exists('count', $response)) {
            $this->debug("$moduleName::$functionName fetched {$response['count']} items");
        } else {
            $this->debug("$moduleName::$functionName did not fetched any items");
        }

        if (key_exists('data', $response) && key_exists('count', $response)) {
            $items = $response['data'];
            foreach ($items as $index => $item) {
                try {
                    $dotObject = new Dot($item);
                    $this->processItem(new Dot($item));
                    unset($dotObject);

                    $count = $index + 1;
                    $this->debug("Processed {$count}/{$response['count']} items");
                } catch (\Exception $exception) {
                    $this->debug('Caught an exception');

                    $errorMessage = $exception->getMessage();
                    $data = [
                        'item' => $item,
                        'exception' => $exception,
                    ];
                    $this->debug("Failed to process with exception: {$errorMessage}", $data);
                    throw $exception;
                }
            }
            unset($items);
            unset($response);
        }
    }

    protected function processItem($dotObject, array $options = []): ?int
    {
        $this->debug('Processing category', $dotObject->get());

        $slug = $dotObject->get('slug');
        $storekeeperId = $dotObject->get('id');
        $term = Categories::getCategoryById($storekeeperId);
        if (false === $term) {
            $term = Categories::getCategoryBySlug($slug);
            $this->debug('Got category by slug='.$slug);
        }

        $isNew = !$term;

        $title = $this->getTranslationIfRequired($dotObject, 'title');
        $description = $this->getTranslationIfRequired($dotObject, 'description');
        $summary = $this->getTranslationIfRequired($dotObject, 'summary');

        if ('' === trim($title)) {
            throw new \Exception('No title set for category id='.$storekeeperId.' slug='.$slug);
        }

        $args = [
            'description' => $description,
            'slug' => $slug,
        ];

        $parsedown = new MarkdownCode();
        $args['description'] = $parsedown->render(null, $description);
        if (!$this->descriptionAsHtml) {
            $args['description'] = strip_tags($args['description']);
        }
        if (!$isNew) {
            $args['name'] = $title;
            if ($term->slug !== $slug) {
                $this->debug('Changing category slug to='.$slug);
            }
        }

        if ($dotObject->get('category_tree.level') > 1 && $dotObject->has('parent_id')) {
            $parent_id = $dotObject->get('parent_id');
            $parent_term = Categories::getCategoryById($parent_id);
            if ((bool) $parent_term) {
                $this->debug('Found parent');
                $args['parent'] = $parent_term->term_id;
            } else {
                $this->debug('Importing parent');
                $parent_category_import = new CategoryImport(
                    [
                        'storekeeper_id' => $parent_id,
                    ]
                );
                $parent_category_import->setLogger($this->logger);
                $parent_category_import->run();
                $parent_term = Categories::getCategoryById($parent_id);
                $args['parent'] = $parent_term->term_id;
            }
        }
        $this->debug('Set parent');

        if ($isNew) {
            $this->debug('New category term');

            if ($dotObject->get('category_tree.level') > 1 && $dotObject->has('parent_id')) {
                $parent_id = $dotObject->get('parent_id');
                $parent_term = Categories::getCategoryById($parent_id);
                $args['parent'] = $parent_term->term_id;
            }

            // Create
            $term = WordpressExceptionThrower::throwExceptionOnWpError(
                wp_insert_term(
                    $title,
                    'product_cat',
                    $args
                )
            );
            $term_id = $term['term_id'];
        } else {
            $this->debug('Found category term');
            // Check if the category is published
            if (!$dotObject->get('published')) {
                Categories::deleteCategoryByTermId($term->term_id);

                return null;
            }

            // Update
            $term = WordpressExceptionThrower::throwExceptionOnWpError(
                wp_update_term(
                    $term->term_id,
                    'product_cat',
                    $args
                )
            );
            $term_id = $term['term_id'];
        }

        // Handle seo
        $this->processSeo(get_term($term_id), $dotObject);

        update_term_meta($term_id, 'storekeeper_id', $storekeeperId);
        // Term summary for at the top of the page.
        update_term_meta($term_id, 'category_summary', ParseDown::wrapContentInShortCode($summary));
        // We store the description also to make sure the html is being preserved.
        // Something the default term description does not do
        update_term_meta($term_id, 'category_description', ParseDown::wrapContentInShortCode($description));

        if (!empty($image_url = $dotObject->get('image_url', false))) {
            self::setImage($term_id, $image_url);
        } else {
            self::unsetImage($term_id);
        }

        if (
            $dotObject->has('category_tree.direct_children')
            && count($dotObject->get('category_tree.direct_children')) > 0
        ) {
            $child_ids = $dotObject->get('category_tree.direct_children');
            foreach ($child_ids as $child_id) {
                $category = Categories::getCategoryById($child_id);
                if (false !== $category) {
                    WordpressExceptionThrower::throwExceptionOnWpError(
                        wp_update_term(
                            $category->term_id,
                            'product_cat',
                            ['parent' => $term_id]
                        )
                    );
                }
            }
        }

        return null;
    }

    /**
     * @throws WordpressException
     */
    protected function processSeo(\WP_Term $term, Dot $dotObject): void
    {
        $seoTitle = null;
        $seoDescription = null;
        $seoKeywords = null;

        if ($dotObject->has('seo_title')) {
            $seoTitle = $dotObject->get('seo_title');
        }

        if ($dotObject->has('seo_description')) {
            $seoDescription = $dotObject->get('seo_description');
        }

        if ($dotObject->has('seo_keywords')) {
            $seoKeywords = $dotObject->get('seo_keywords');
        }

        if (YoastSeo::isSelectedHandler() && YoastSeo::shouldAddSeo($seoTitle, $seoDescription, $seoKeywords)) {
            YoastSeo::addSeoToCategory($term->term_id, $seoTitle, $seoDescription, $seoKeywords);
        }

        if (RankMathSeo::isSelectedHandler() && RankMathSeo::shouldAddSeo($seoTitle, $seoDescription, $seoKeywords)) {
            RankMathSeo::addSeoToCategory($term->term_id, $seoTitle, $seoDescription, $seoKeywords);
        }

        StoreKeeperSeo::setCategorySeo($term, $seoTitle, $seoDescription, $seoKeywords);
    }

    private function enableAllowHtmlInDescriptions()
    {
        remove_filter('term_description', 'wp_kses_data');
        remove_filter('pre_term_description', 'wp_filter_kses');
        add_filter('term_description', 'wp_kses_post');
        add_filter('pre_term_description', 'wp_filter_post_kses');
    }

    private static function setImage($term_id, $image_url)
    {
        $media_id = Media::ensureAttachment($image_url);

        update_term_meta($term_id, 'thumbnail_id', $media_id);

        if (PluginStatus::isPortoFunctionalityEnabled()) {
            update_metadata('product_cat', $term_id, 'category_image', wp_get_attachment_image_url($media_id));
        }
    }

    private static function unsetImage($term_id)
    {
        delete_term_meta($term_id, 'thumbnail_id');

        if (PluginStatus::isPortoFunctionalityEnabled()) {
            delete_metadata('product_cat', $term_id, 'category_image');
        }
    }

    protected function getImportEntityName(): string
    {
        return __('product categories', I18N::DOMAIN);
    }
}
