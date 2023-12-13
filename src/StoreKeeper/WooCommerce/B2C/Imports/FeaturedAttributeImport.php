<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\WithConsoleProgressBarInterface;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Traits\ConsoleProgressBarTrait;

class FeaturedAttributeImport extends AbstractImport implements WithConsoleProgressBarInterface
{
    use ConsoleProgressBarTrait;

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
    protected function processItem($dotObject, array $options = []): ?int
    {
        FeaturedAttributeOptions::setAttribute(
            $dotObject->get('alias'),
            $dotObject->get('attribute_id'),
            $dotObject->get('attribute.name')
        );

        return null;
    }

    protected function getImportEntityName(): string
    {
        return __('featured attributes', I18N::DOMAIN);
    }
}
