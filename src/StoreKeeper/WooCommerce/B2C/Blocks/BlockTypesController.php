<?php

namespace StoreKeeper\WooCommerce\B2C\Blocks;

use StoreKeeper\WooCommerce\B2C\I18N;

class BlockTypesController
{

    /**
     * StoreKeeper block category slug
     */
    public const BLOCK_CATEGORY = 'storekeeper';

    /**
     * StoreKeeper block type namespace
     */
    public const NAMESPACE = 'storekeeper';

    /**
     * StoreKeeper block type register handlers
     */
    protected const BLOCK_REGISTER_HANDLERS = [
        RegisterHandlers\Location::class
    ];

    /**
     * StoreKeeper's initialized block types
     */
    protected $registeredBlockTypes = [];

    /**
     * Initialize
     */
    public function __construct()
    {
        add_action('init', [$this, 'registerBlocks']);

        if (version_compare(get_bloginfo('version'), '5.8', '>=')) {
            add_filter( 'block_categories_all', [$this, 'registerCategory'] );
        } else {
            add_filter( 'block_categories', [$this, 'registerCategory'] );
        }
    }

    /**
     * Initialize StoreKeeper block type registration
     *
     * @return void
     */
    public function registerBlocks()
    {
        $this->registeredBlockTypes = array_reduce(
            $this->getRegisterHandlers(),
            function (array $blockTypes, $registrar) {
                if (is_a($registrar, BlockTypeRegistrarInterface::class, true)) {
                    $blockType = (new $registrar())->register();

                    if ($blockType) {
                        $blockTypes[$blockType->name] = $blockType;
                    }

                    return $blockTypes;
                }
            },
            $this->registeredBlockTypes
        );
    }

    /**
     * Register StoreKeeper block type category
     *
     * @return void
     */
    public function registerCategory($categories)
    {
        $icon = null;

        return array_merge(
            $categories,
            [
                [
                    'slug'  => self::BLOCK_CATEGORY,
                    'title' => __('StoreKeeper', I18N::DOMAIN),
                    'icon' => $icon
                ]
            ]
        );
    }

    /**
     * Get list of block register handlers.
     *
     * @return array
     */
    protected function getRegisterHandlers()
    {
        return self::BLOCK_REGISTER_HANDLERS;
    }
}
