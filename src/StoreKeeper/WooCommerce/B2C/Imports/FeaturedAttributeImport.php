<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeExportOptions;

class FeaturedAttributeImport extends AbstractImport
{
    protected function getModule()
    {
        return 'ProductsModule';
    }

    protected function getFunction()
    {
        return 'listFeaturedAttributes';
    }

    protected function getSorts()
    {
        return [
            [
                'name' => 'alias',
                'dir' => 'asc',
            ],
        ];
    }

    /**
     * @param Dot $dotObject
     *
     * @throws \Exception
     */
    protected function processItem($dotObject, array $options = [])
    {
        FeaturedAttributeExportOptions::setAttribute(
            $dotObject->get('alias'),
            $dotObject->get('attribute_id'),
            $dotObject->get('attribute.name')
        );
    }
}
