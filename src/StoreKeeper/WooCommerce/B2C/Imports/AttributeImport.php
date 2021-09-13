<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;
use StoreKeeper\WooCommerce\B2C\Tools\AttributeTranslator;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use StoreKeeper\WooCommerce\B2C\Tools\WooCommerceAttributeMetadata;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use function wc_create_attribute;
use function wc_get_attribute;
use function wc_update_attribute;

class AttributeImport extends AbstractImport
{
    /**
     * This value is used to limite the import of attributes.
     *
     * @var int
     */
    private $storekeeper_id = 0;

    /**
     * AttributeImport constructor.
     *
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        $this->storekeeper_id = key_exists('storekeeper_id', $settings) ? (int) $settings['storekeeper_id'] : 0;
        unset($settings['storekeeper_id']);
        parent::__construct($settings);
    }

    public static function getMainLanguage()
    {
        return Language::getSiteLanguageIso2();
    }

    protected function getModule()
    {
        return 'BlogModule';
    }

    protected function getFunction()
    {
        return 'listTranslatedAttributes';
    }

    protected function getFilters()
    {
        $f = [
            [
                'name' => 'is_options__=',
                'val' => '1',
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
     * @param Dot $dotObject
     *
     * @throws \Exception
     */
    protected function processItem($dotObject, array $options = [])
    {
        $slug = $dotObject->get('name');

        $attributeCheck = Attributes::getAttribute($dotObject->get('id'));
        if (empty($attributeCheck) && $dotObject->has('name')) {
            $attributeCheck = Attributes::getAttributeBySlug($slug);
        }

        $title = $this->getTranslationIfRequired($dotObject, 'label');

        if (empty(trim($title))) {
            // fallback in case if empty
            $title = $dotObject->get('name');
        }

        $update_arguments = [
            'name' => substr($title, 0, 30),
        ];

        // If the slug is set in the update_arguments and if it is not a new attribute,
        // then you need to use the update_attribute function
        if (false !== $attributeCheck) {
            $update_arguments['type'] = $attributeCheck->type;
            $update_arguments['slug'] = $attributeCheck->slug;
            $attribute = WordpressExceptionThrower::throwExceptionOnWpError(wc_get_attribute($attributeCheck->id));
            if ($attribute) {
                $update_arguments['has_archives'] = $attribute->has_archives;
            }

            // Update the attribute in woocommerce
            $attribute_id = WordpressExceptionThrower::throwExceptionOnWpError(
                wc_update_attribute($attributeCheck->id, $update_arguments)
            );
        } else {
            $update_arguments['type'] = Attributes::getDefaultType();
            $update_arguments['slug'] = AttributeTranslator::validateAttribute($slug);
            if (!array_key_exists('has_archives', $update_arguments)) {
                $update_arguments['has_archives'] = Attributes::DEFAULT_ARCHIVED_SETTING; // Activate archives when it is not set
            }

            // Create the attribute in woocommerce
            $attribute_id = WordpressExceptionThrower::throwExceptionOnWpError(wc_create_attribute($update_arguments));
        }

        WooCommerceAttributeMetadata::setMetadata($attribute_id, 'storekeeper_id', $dotObject->get('id'));
        WooCommerceAttributeMetadata::setMetadata($attribute_id, 'attribute_order', $dotObject->get('order'));
    }
}
