<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;

class AttributeOptionImport extends AbstractImport
{
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
        $this->storekeeper_id = key_exists('storekeeper_id', $settings) ? (int) $settings['storekeeper_id'] : 0;
        $this->attribute_id = key_exists('attribute_id', $settings) ? (int) $settings['attribute_id'] : 0;
        $this->attribute_option_ids = key_exists(
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
    protected function processItem($dotObject, array $options = [])
    {
        $this->debug('Importing Attribute option with id', $dotObject->get());
        $title = $this->getTranslationIfRequired($dotObject, 'label');

        $attribute_slug = $dotObject->get('attribute.name');
        $attribute_name = $dotObject->get('attribute.label');
        $attribute_option_id = $dotObject->get('id');
        $attribute_option_name = substr($title, 0, 30);
        $attribute_option_slug = Attributes::sanitizeOptionSlug($attribute_option_id, $dotObject->get('name'));
        $attribute_option_image = $dotObject->get('image_url', false);
        $attribute_option_order = $dotObject->get('order', false);

        Attributes::updateAttributeOption(
            $attribute_slug,
            $attribute_name,
            $attribute_option_id,
            $attribute_option_slug,
            $attribute_option_name,
            $attribute_option_image,
            $attribute_option_order
        );
    }
}
