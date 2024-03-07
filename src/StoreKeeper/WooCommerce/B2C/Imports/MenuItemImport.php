<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\Categories;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class MenuItemImport extends AbstractImport
{
    /**
     * @var int the storekeeper id
     */
    private $storekeeper_id;

    /**
     * AttributeOptionImport constructor.
     *
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        $this->storekeeper_id = key_exists('storekeeper_id', $settings) ? (int) $settings['storekeeper_id'] : 0;
        unset($settings['storekeeper_id'], $settings['attribute_id'], $settings['attribute_option_ids']);
        parent::__construct($settings);
    }

    /**
     * @return string
     */
    protected function getModule()
    {
        return 'ShopModule';
    }

    /**
     * @return string
     */
    protected function getFunction()
    {
        return 'listMenuItemsForHooks';
    }

    /**
     * @return null
     */
    protected function getLanguage()
    {
        return null;
    }

    /**
     * @return array
     */
    protected function getFilters()
    {
        $filters = [];
        if ($this->storekeeper_id > 0) {
            $filters[] = [
                'name' => 'id__=',
                'val' => $this->storekeeper_id,
            ];
        }

        return $filters;
    }

    /**
     * @param Dot $dotObject
     *
     * @throws \Exception
     */
    protected function processItem($dotObject, array $options = []): ?int
    {
        $menuItemId = $dotObject->get('id');
        $menuAlias = $dotObject->get('menu.alias');
        $menuId = $dotObject->get('menu.id');

        // Ensures that the menu exists or is created, returns the term id of the menu
        $termMenuId = $this->ensureMenu($menuAlias, $menuId);

        // Gets the post of the menu item based on its storekeeper_id
        $termMenuItemId = self::getMenuItemId($menuItemId);

        // Gets the item config
        $menuItemConfig = $this->getMenuItemConfig($dotObject);

        // Creates or updates the menu item ($termMenuItemId can be 0. If "0", creates a new menu item.)
        $postId = WordpressExceptionThrower::throwExceptionOnWpError(
            wp_update_nav_menu_item(
                $termMenuId,
                $termMenuItemId,
                $menuItemConfig
            )
        );

        // Creates or updates the post meta
        update_post_meta($postId, 'menu_item_storekeeper_id', $menuItemId);

        $this->debug('Processed menu item');

        return null;
    }

    /**
     * @param string $menuAlias
     * @param int    $menuId
     *
     * @return int|void
     *
     * @throws \Exception
     */
    protected function ensureMenu($menuAlias, $menuId)
    {
        $termId = $this->getMenuTermId($menuId);
        if ($termId <= 0) {
            // Creates the menu if it does not exist yet
            $this->debug("Creating menu with title $menuAlias");
            $createdMenuId = $this->createMenu($menuAlias, $menuId);
            if (!is_int($createdMenuId)) {
                throw new \Exception('Error creating menu.');
            }
            $termId = $createdMenuId;
        }

        return $termId;
    }

    /**
     * @param Dot $deebObjectItem
     *
     * @return array
     *
     * @throws \Exception
     */
    public static function getMenuItemConfig($deebObjectItem)
    {
        // Get the title from the deep object item
        $title = $deebObjectItem->get('title');

        $type = self::getMenuItemType($deebObjectItem);
        $vars = self::processItemVars($deebObjectItem->get('menu_item_vars'));

        $config = [
            'menu-item-title' => $title,
            'menu-item-classes' => 'home',
            'menu-item-status' => 'publish',
            'menu-item-url' => '',
        ];

        switch ($type) {
            case 'BlogModule::Category':
                $categoryId = &$vars['category_id'];
                if (null != $categoryId) {
                    // Find the termlink of the category
                    $term = Categories::getCategoryById($categoryId);
                    if (!is_wp_error($term)) {
                        // Set the url to the term link
                        $config['menu-item-url'] = get_term_link($term);
                    } else {
                        throw new \Exception("Term link could not be found for the category id $categoryId (is it synced yet?)");
                    }
                } else {
                    throw new \Exception("$title has type BlogModule::Category but is missing \"category_id\" item variable.");
                }
                break;
            case 'BlogModule::Link':
                $url = &$vars['url'];
                if (null != $url) {
                    // Set the url to the given item url
                    $config['menu-item-url'] = $url;
                } else {
                    throw new \Exception("$title has type BlogModule::Link but is missing \"url\" item variable.");
                }
                break;
            case 'ShopModule::ShopProduct':
                $shopProductId = &$vars['shop_product_id'];
                if (null != $shopProductId) {
                    // Find the permalink to the product
                    $permalink = self::getPermalinkById((int) $shopProductId, 'product');
                    if ($permalink) {
                        // Set the link to found permalink
                        $config['menu-item-url'] = $permalink;
                    } else {
                        throw new \Exception("Permalink of product $shopProductId not found.");
                    }
                } else {
                    throw new \Exception("$title has type ShopModule::ShopProduct but is missing \"shop_product_id\" item variable.");
                }
                break;
            case 'ShopModule::ManagedWebShopPage':
                $contentId = &$vars['content_id'];
                if (null != $contentId) {
                    // Find the permalink to the page
                    $permalink = self::getPermalinkById(
                        (int) $contentId,
                        'page'
                    ); // TODO [ian@3/18/20]: Unable to make pages for a Wordpress site in the backoffice
                    if ($permalink) {
                        // Set the link to found permalink of the page
                        $config['menu-item-url'] = $permalink;
                    } else {
                        throw new \Exception("Permalink of page $contentId not found.");
                    }
                } else {
                    throw new \Exception("$title has type ShopModule::ManagedWebShopPage but is missing \"content_id\" item variable.");
                }
                break;
            default:
                break;
        }

        if ($deebObjectItem->has('parent_id')) {
            $config['menu-item-parent-id'] = self::getMenuItemId($deebObjectItem->get('parent_id'));
        }

        return $config;
    }

    /**
     * @param int $storekeeper_id
     *
     * @return int
     */
    public static function getMenuTermId($storekeeper_id)
    {
        // Search through the terms for the menu storekeeper id
        $args = [
            'taxonomy' => 'nav_menu',
            'hide_empty' => false,
            'meta_query' => [
                [
                    'key' => 'menu_storekeeper_id',
                    'value' => strval($storekeeper_id),
                    'compare' => '=', // compare
                ],
            ],
        ];
        // Get the terms with the arguments
        $terms = get_terms($args);

        return sizeof($terms) > 0 ? $terms[0]->term_id : 0;
    }

    /**
     * @return \WP_Term|null
     *
     * @throws WordpressException
     */
    public static function getMenuTerm($storekeeper_id)
    {
        $terms = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => 'nav_menu',
                    'hide_empty' => false,
                    'meta_query' => [
                        [
                            'key' => 'menu_storekeeper_id',
                            'value' => strval($storekeeper_id),
                            'compare' => '=', // compare
                        ],
                    ],
                ]
            )
        );

        if (1 === count($terms)) {
            return $terms[0];
        }
    }

    /**
     * @param int $storekeeper_id
     *
     * @return int
     */
    protected static function getMenuItemId($storekeeper_id)
    {
        // Search through the posts for the menu item storekeeper id
        $posts = get_posts(
            [
                'post_type' => 'nav_menu_item',
                'meta_query' => [
                    [
                        'key' => 'menu_item_storekeeper_id',
                        'value' => strval($storekeeper_id),
                        'compare' => '=', // compare
                    ],
                ],
            ]
        );
        $postId = sizeof($posts) > 0 ? $posts[0]->ID : 0;

        return $postId;
    }

    /**
     * @return \WP_Post|null
     *
     * @throws WordpressException
     */
    public static function getMenuItem($storekeeper_id)
    {
        $posts = WordpressExceptionThrower::throwExceptionOnWpError(
            get_posts(
                [
                    'post_type' => 'nav_menu_item',
                    'meta_query' => [
                        [
                            'key' => 'menu_item_storekeeper_id',
                            'value' => strval($storekeeper_id),
                            'compare' => '=', // compare
                        ],
                    ],
                ]
            )
        );

        if (1 === count($posts)) {
            return $posts[0];
        }
    }

    /**
     * @return int|void
     *
     * @throws \Exception
     */
    protected function createMenu($menuAlias, $menuId)
    {
        // Create the menu in wordpress
        $termMenuId = WordpressExceptionThrower::throwExceptionOnWpError(wp_create_nav_menu($menuAlias));

        // Add the term meta to the menu
        if (null != $menuId) {
            add_term_meta($termMenuId, 'menu_storekeeper_id', $menuId);
        }

        return $termMenuId;
    }

    /**
     * Gets the permalink of the content id.
     *
     * @param int    $contentId
     * @param string $postType
     *
     * @return string
     *
     * @throws \Exception
     */
    protected static function getPermalinkById($contentId, $postType)
    {
        // Gets the permalink for the id
        $args = [
            'post_type' => $postType,
            'meta_query' => [
                [
                    'key' => 'storekeeper_id',
                    'value' => $contentId,
                    'compare' => '=', // compare
                ],
            ],
        ];
        $posts = get_posts($args);
        if (sizeof($posts) < 1) {
            throw new \Exception("No post meta could be found for post $contentId with post type $postType.");
        }
        $postId = $posts[0]->ID;
        $permalink = get_permalink((int) $postId);
        if (!$permalink) {
            throw new \Exception("No permalink could be found for post $contentId with post type $postType.");
        }

        return $permalink;
    }

    /**
     * @param string $location the navigation location
     *
     * @return bool
     */
    protected function isValidLocation($location)
    {
        // Check if the given location is valid
        $validLocations = get_nav_menu_locations();

        return array_key_exists($location, $validLocations);
    }

    /**
     * @param Dot $dotObject
     *
     * @return string the type found
     */
    protected static function getMenuItemType($dotObject)
    {
        $parsedType = $dotObject->get('menu_item_type.module_name').'::'.$dotObject->get(
            'menu_item_type.alias'
        );

        return $parsedType;
    }

    /**
     * @param array $itemVars the array of item variables that need to be processed
     *
     * @return array the easy accessible array of item variables
     */
    public static function processItemVars($itemVars)
    {
        $processedItemVars = [];

        if ($itemVars) {
            // Create key-value object for the item variables
            foreach ($itemVars as $itemVar) {
                $processedItemVars[$itemVar['name']] = $itemVar['value'];
            }
        }

        return $processedItemVars;
    }

    protected function getImportEntityName(): string
    {
        return __('menu items', I18N::DOMAIN);
    }
}
