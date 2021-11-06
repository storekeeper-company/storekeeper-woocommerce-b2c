<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeExportOptions;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Query\ProductQueryBuilder;

class ProductAttributes
{
    public static function setSimpleAttributes(\WC_Product $newProduct, Dot $product)
    {
        $attribute_data = [];
        if ($product->has('flat_product.content_vars')) {
            $attribute_data = self::getAttributeData($product);
        }

        if (count($attribute_data) > 0) {
            $attributes = \WC_Meta_Box_Product_Data::prepare_attributes($attribute_data);
            $newProduct->set_attributes($attributes);
        }

        self::setBarcodeMeta($newProduct, $product);
    }

    public static function setBarcodeMeta(\WC_Product $newProduct, Dot $product)
    {
        $barcode = FeaturedAttributeExportOptions::getWooCommerceAttributeName(FeaturedAttributes::ALIAS_BARCODE);
        if ($barcode) {
            $meta_key = StoreKeeperOptions::getBarcodeMetaKey($newProduct);
            $barcode_was_set = false;
            if ($product->has('flat_product.content_vars')) {
                foreach ($product->get('flat_product.content_vars') as $cvData) {
                    $contentVar = new Dot($cvData);
                    if ($contentVar->get('name') === $barcode) {
                        $value = $contentVar->get('value');
                        $newProduct->add_meta_data($meta_key, $value, true);
                        $barcode_was_set = true;
                        break;
                    }
                }
            }
            if (!$barcode_was_set) {
                $newProduct->delete_meta_data($meta_key);
            }
        }
    }

    public static function getBarcodeMeta(\WC_Product $wc_product)
    {
        return $wc_product->get_meta(StoreKeeperOptions::getBarcodeMetaKey($wc_product));
    }

    public static function getAttributeData(Dot $product)
    {
        $attributeData = [
            'attribute_names' => [],
            'attribute_position' => [],
            'attribute_visibility' => [],
            'attribute_values' => [],
        ];

        $added = 0;

        if ($product->has('flat_product.content_vars')) {
            foreach ($product->get('flat_product.content_vars') as $index => $cvData) {
                $contentVar = new Dot($cvData);

                if (!$contentVar->has('attribute_id')) {
                    continue;
                }

                if (!$contentVar->get('attribute_published')) {
                    continue;
                }

                ++$added;

                // Check if attribute and attribute option id exists.
                $attribute = Attributes::getAttribute($contentVar->get('attribute_id'));
                $attributeOptionsId = false;
                if ($contentVar->has('attribute_option_id') && $attribute && $attribute->slug && $attribute->name) {
                    $attributeOptionsId = Attributes::getAttributeOptionTermIdByAttributeOptionId(
                        $contentVar->get('attribute_option_id'),
                        $attribute->slug
                    );
                }

                // Check if attribute and or attribute option needs updating
                $updateAttribute = !$attribute;
                $updateAttributeOptions = !$attributeOptionsId;
                if (!$updateAttributeOptions && $contentVar->has('attribute_option_id')) {
                    $attribute_option_term = get_term($attributeOptionsId, $attribute->slug);
                    $updateAttributeOptions = $attribute_option_term->name !== $contentVar->has('value_label');
                }

                // Update both attribute and attribute options if either need updating
                if ($updateAttributeOptions || $updateAttribute) {
                    $attributeOptionsId = Attributes::updateAttributeAndOptionFromContentVar($contentVar->get());
                    $attribute = Attributes::getAttribute($contentVar->get('attribute_id'));
                }

                if ($contentVar->has('attribute_option_id')) {
                    $attributeData['attribute_names'][$index] = $attribute->slug;
                } else {
                    $attributeData['attribute_names'][$index] = $contentVar->get('label');
                }

                $attributeOrder = 0;
                if ($contentVar->has('attribute_order')) {
                    $attributeOrder = $contentVar->get('attribute_order');
                }
                $attributeData['attribute_position'][$index] = (int) $attributeOrder;

                if ($contentVar->get('attribute_published')) {
                    $attributeData['attribute_visibility'][$index] = 1;
                }

                // If the attribute options is imported
                if ($attributeOptionsId) {
                    $attributeData['attribute_values'][$index] = [
                        $attributeOptionsId,
                    ];
                } else {
                    if ($contentVar->has('value_label')) {
                        $attributeData['attribute_values'][$index] = Attributes::sanitizeOptionSlug(
                            $contentVar->get('attribute_option_id'),
                            (string) $contentVar->get('value')
                        );
                    } else {
                        $attributeData['attribute_values'][$index] = (string) $contentVar->get('value');
                    }
                }
            }
        }
        if ($added <= 0) {
            return [];
        }

        return $attributeData;
    }

    public static function getCustomProductAttributeOptions()
    {
        global $wpdb;

        $query = ProductQueryBuilder::getCustomProductAttributes();
        $results = $wpdb->get_results($query);
        foreach ($results as $result) {
            if ($attributes = unserialize($result->attributes)) {
                if (!empty($attributes)) {
                    foreach ($attributes as $attributeName => $attribute) {
                        if (0 === $attribute['is_taxonomy']) {
                            $attribute['post_id'] = $result->ID;
                            yield $attributeName => $attribute;
                        }
                    }
                }
            }
        }
    }

    public static function setConfigurableAttributes(\WC_Product $newProduct, Dot $product, Dot $optionsConfig)
    {
        $attribute_data = [
            'attribute_names' => [],
            'attribute_position' => [],
            'attribute_visibility' => [],
            'attribute_values' => [],
        ];

        if ($product->has('flat_product.content_vars')) {
            $attribute_data = ProductAttributes::getAttributeData($product);
        }

        /**
         * We are going to create an attribute option id map, so we can limit the attribute options we recheck
         * $attribute_option_id_map[$attribute_id] = [...$attribute_option_id].
         */
        $attribute_option_id_map = [];
        $attribute_options = $optionsConfig->get('attribute_options');

        if (is_array($attribute_options)) {
            foreach ($attribute_options as $attribute_option) {
                if (!array_key_exists($attribute_option['attribute_id'], $attribute_option_id_map)) {
                    $attribute_option_id_map[$attribute_option['attribute_id']] = [];
                }

                $attribute_option_id_map[$attribute_option['attribute_id']][] = $attribute_option['id'];
            }
        }

        // Checking if the optionsConfig attributes are fully synced, limiting to the required attribute options ids.
        $attribute_ids = $optionsConfig->get(
            'configurable_product.configurable_product_kind.configurable_attribute_ids'
        );
        if (is_array($attribute_ids)) {
            foreach ($attribute_ids as $attribute_id) {
                if (array_key_exists($attribute_id, $attribute_option_id_map)) {
                    $A = new Attributes();
                    $A->ensureAttributeAndOptions($attribute_id, $attribute_option_id_map[$attribute_id]);
                }
            }
        }

        $configurable_attribute_array = [];

        foreach ($optionsConfig->get('attributes') as $attribute) {
            $term_name = Attributes::createWooCommerceAttributeName($attribute['name']);
            $configurable_attribute_array[$term_name] = [];
            foreach ($optionsConfig->get('attribute_options') as $attribute_option) {
                $attribute_options_id = Attributes::getAttributeOptionTermId(
                    $attribute['name'],
                    $attribute_option['name'],
                    $attribute_option['id']
                );

                if ($attribute_options_id) {
                    $attributeOptionsOrder = $attribute_option['order'] ?? 0;
                    Attributes::updateAttributeOptionOrder($attribute_options_id, $attributeOptionsOrder);

                    $configurable_attribute_array[$term_name][] = $attribute_options_id;
                }
            }
        }

        foreach ($configurable_attribute_array as $attribute_name => $attribute_values) {
            $index = array_search($attribute_name, $attribute_data['attribute_names'], true);

            if (false === $index) {
                $index = count($attribute_data['attribute_position']) + 1;
                $attribute_data['attribute_position'][$index] = count($attribute_data['attribute_position']);
            }

            $attribute_data['attribute_names'][$index] = $attribute_name;
            $attribute_data['attribute_visibility'][$index] = 1;

            $attribute_data['attribute_values'][$index] = $attribute_values;
            $attribute_data['attribute_variation'][$index] = 1;
        }

        $attributes = \WC_Meta_Box_Product_Data::prepare_attributes($attribute_data);
        $newProduct->set_attributes($attributes);

        ProductAttributes::setBarcodeMeta($newProduct, $product);
    }

    public static function setAssignedAttributes(\WC_Product $newProduct, Dot $optionsConfig, array $wantedAttributeOptionIds)
    {
        $options = [];

        $attributes = self::getArrayById($optionsConfig->get('attributes'));
        $attributeOptions = self::getArrayById($optionsConfig->get('attribute_options'));

        foreach ($wantedAttributeOptionIds as $wantedId) {
            if (array_key_exists($wantedId, $attributeOptions)) {
                $option = $attributeOptions[$wantedId];
                $attribute = $attributes[$option['attribute_id']];
                $attrName = wc_variation_attribute_name(Attributes::createWooCommerceAttributeName($attribute['name']));
                $options[$attrName] = Attributes::sanitizeOptionSlug($option['id'], $option['name']);
            }
        }

        $newProduct->set_attributes($options);
    }

    public static function getAssignedWantedAttributes(Dot $assignedProductData, array $attribute_options_by_id): array
    {
        $wanted_configured_attribute_option_ids = [];
        foreach ($assignedProductData->get('flat_product.content_vars', []) as $content_var) {
            Attributes::updateAttributeAndOptionFromContentVar($content_var);
            if (
                array_key_exists('attribute_option_id', $content_var) &&
                array_key_exists($content_var['attribute_option_id'], $attribute_options_by_id)
            ) {
                $wanted_configured_attribute_option_ids[] = $content_var['attribute_option_id'];
            }
        }

        return $wanted_configured_attribute_option_ids;
    }

    private static function getArrayById($array, $key = 'id')
    {
        $return = [];
        foreach ($array as $item) {
            $return[$item[$key]] = $item;
        }

        return $return;
    }
}
