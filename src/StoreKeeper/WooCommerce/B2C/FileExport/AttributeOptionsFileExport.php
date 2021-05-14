<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;
use StoreKeeper\WooCommerce\B2C\Tools\Export\AttributeExport;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

class AttributeOptionsFileExport extends AbstractCSVFileExport
{
    public function getType(): string
    {
        return FileExportTypeHelper::ATTRIBUTE_OPTION;
    }

    public function getPaths(): array
    {
        return [
            'name' => 'Name',
            'label' => 'Label',
            'translatable.lang' => 'Language',
            'is_main_lang' => 'Is main language',
            'is_default' => 'Is default',
            'image_url' => 'Image URL',
            'attribute.name' => 'Attribute name',
            'attribute.label' => 'Attribute label',
            'date_created' => 'Date created',
            'date_updated' => 'Date updated',
        ];
    }

    /**
     * Runs the export, once done it returns the path to the exported file.
     */
    public function runExport(string $exportLanguage = null): string
    {
        $featuredAttributes = FeaturedAttributeOptions::getMappedFeaturedExportAttributes();
        $exportLanguage = $exportLanguage ?? Language::getSiteLanguageIso2();
        $options = array_merge(
            $this->getGenericOptions(),
            $this->getProductOptions()
        );

        $total = count($options);
        foreach (array_values($options) as $index => $option) {
            if (!array_key_exists($option['attribute_name'], $featuredAttributes)) {
                $lineData = [];
                $lineData['name'] = $option['name'];
                $lineData['label'] = $option['label'];
                $lineData['translatable.lang'] = $exportLanguage;
                $lineData['attribute.name'] = $option['attribute_name'];
                $lineData['attribute.label'] = $option['attribute_label'];

                $this->writeLineData($lineData);

                if (0 === $index % 25) {
                    $this->reportUpdate($total, $index, 'Exported 25 attribute options');
                }
            }
        }

        return $this->filePath;
    }

    private function getGenericOptions(): array
    {
        $map = $this->keyValueMapArray(
            wc_get_attribute_taxonomies(),
            function ($item) {
                return wc_attribute_taxonomy_name($item->attribute_name);
            }
        );

        $options = [];
        foreach ($map as $name => $attribute) {
            $attributeOptions = get_terms($name, ['hide_empty' => false]);
            foreach ($attributeOptions as $attributeOption) {
                $attribute = $map[$attributeOption->taxonomy];

                $options[] = [
                    'name' => $attributeOption->slug,
                    'label' => $attributeOption->name,
                    'attribute_name' => AttributeExport::getAttributeKey(
                        $attribute->attribute_name,
                        AttributeExport::TYPE_SYSTEM_ATTRIBUTE
                    ),
                    'attribute_label' => $attribute->attribute_label,
                ];
            }
        }

        return $options;
    }

    private function getProductOptions(): array
    {
        $productAttributeWithOptionsMap = Attributes::getAttributesWithOptionsMap();
        $options = [];

        $next = true;
        $index = 0;
        while ($next) {
            $attributes = Attributes::getProductAttributesAtIndex($index++);
            $next = (bool) $attributes;

            if (is_array($attributes)) {
                foreach ($attributes as $attributeName => $attribute) {
                    if (0 === $attribute['is_taxonomy'] && $productAttributeWithOptionsMap[$attributeName]) {
                        $attributeOptions = wc_get_text_attributes($attribute['value']);
                        foreach ($attributeOptions as $label) {
                            $name = sanitize_title($label);
                            if (empty($options[$name])) {
                                $options[$name] = [
                                    'name' => $name,
                                    'label' => $label,
                                    'attribute_name' => AttributeExport::getAttributeKey(
                                        $attributeName,
                                        AttributeExport::TYPE_CUSTOM_ATTRIBUTE
                                    ),
                                    'attribute_label' => $attribute['name'],
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $options;
    }
}
