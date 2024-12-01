<?php

namespace StoreKeeper\WooCommerce\B2C\Blocks\RegisterHandlers;

use StoreKeeper\WooCommerce\B2C\Blocks\AbstractBlockTypeRegistrar;
use StoreKeeper\WooCommerce\B2C\Blocks\BlockTypes\Location\Renderer;

class Location extends AbstractBlockTypeRegistrar
{

    /**
     * @inheritDoc
     */
    public static function getArgs()
    {
        return \array_merge(
            parent::getArgs(),
            [
                'render_callback' => [Renderer::class, 'render']
            ]
        );
    }
}
