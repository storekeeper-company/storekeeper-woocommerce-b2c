<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\WithConsoleProgressBarInterface;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;
use StoreKeeper\WooCommerce\B2C\Traits\ConsoleProgressBarTrait;

class AttributeOptionImport extends AbstractImport implements WithConsoleProgressBarInterface
{
    use ConsoleProgressBarTrait;

    private $storekeeper_id = 0;

    private $attribute_id = 0;

    private $attribute_option_ids = [];

    /**
     * AttributeOptionImport constructor.
     *
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        if (array_key_exists('hide-progress-bar', $settings)) {
            $this->setIsProgressBarShown(false);
        }
        $this->storekeeper_id = array_key_exists('storekeeper_id', $settings) ? (int) $settings['storekeeper_id'] : 0;
        $this->attribute_id = array_key_exists('attribute_id', $settings) ? (int) $settings['attribute_id'] : 0;
        $this->attribute_option_ids = array_key_exists(
            'attribute_option_ids',
            $settings
        ) ? (array) $settings['attribute_option_ids'] : [];
        unset($settings['storekeeper_id'], $settings['attribute_id'], $settings['attribute_option_ids']);
        parent::__construct($settings);
    }

    protected function getModule()
    {
        return 'BlogModule';
    }

    protected function getFunction()
    {
        return 'listTranslatedAttributeOptions';
    }

    protected function getFilters()
    {
        $f = [];

        if ($this->storekeeper_id > 0) {
            $f[] = [
                'name' => 'id__=',
                'val' => $this->storekeeper_id,
            ];
        }

        if (count($this->attribute_option_ids) > 0) {
            $f[] = [
                'name' => 'id__in_list',
                'multi_val' => $this->attribute_option_ids,
            ];
        }

        if ($this->attribute_id > 0) {
            $f[] = [
                'name' => 'attribute_id__=',
                'val' => $this->attribute_id,
            ];
        }

        return $f;
    }

    /**
     * @param Dot $dotObject
     *
     * @throws \Exception
     * @throws WordpressException
     */
    protected function processItem($dotObject, array $options = []): ?int
    {
        $this->debug('Importing Attribute option with id', $dotObject->get());
        $dotObject->set('label', $this->getTranslationIfRequired($dotObject, 'label'));

        $attribute = new Attributes($this->logger);
        $attribute_id = $attribute->importAttribute(
            $dotObject->get('attribute.id'),
            $dotObject->get('attribute.name'),
            $dotObject->get('attribute.label')
        );
        $attribute->importAttributeOption(
            $attribute_id,
            $dotObject->get('id'),
            $dotObject->get('name'),
            $dotObject->get('label'),
            $dotObject->get('image_url'),
            $dotObject->get('order', 0),
        );

        return null;
    }

    protected function getImportEntityName(): string
    {
        return __('attribute options', I18N::DOMAIN);
    }
}
