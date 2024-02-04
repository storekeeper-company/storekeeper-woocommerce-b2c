<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use Adbar\Dot;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Query\ProductQueryBuilder;

class ProductAttributes implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private const ATTRIBUTE_NAMES = 'attribute_names';
    private const ATTRIBUTE_POSITION = 'attribute_position';
    private const ATTRIBUTE_VISIBILITY = 'attribute_visibility';
    private const ATTRIBUTE_VALUES = 'attribute_values';
    private const ATTRIBUTE_VARIATION = 'attribute_variation';

    private const ATTRIBUTE_KEYS = [
        self::ATTRIBUTE_NAMES,
        self::ATTRIBUTE_POSITION,
        self::ATTRIBUTE_VISIBILITY,
        self::ATTRIBUTE_VALUES,
        self::ATTRIBUTE_VARIATION,
    ];

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    public function setSimpleAttributes(\WC_Product $newProduct, Dot $product)
    {
        $attribute_box = [];
        if ($product->has('flat_product.content_vars')) {
            $attribute_box = $this->getAttributeBoxFromContentVars(
                $product->get('flat_product.content_vars', [])
            );
        }

        if (count($attribute_box) > 0) {
            $attributes = self::prepareBoxData($attribute_box);
            $newProduct->set_attributes($attributes);
        }

        self::setBarcodeMeta($newProduct, $product);
    }

    public static function setBarcodeMeta(\WC_Product $newProduct, Dot $product): bool
    {
        $barcode_was_set = false;
        $barcode = FeaturedAttributeOptions::getWooCommerceAttributeName(FeaturedAttributes::ALIAS_BARCODE);
        if ($barcode) {
            $meta_key = StoreKeeperOptions::getBarcodeMetaKey($newProduct);
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

        return $barcode_was_set;
    }

    public static function getBarcodeMeta(\WC_Product $wc_product)
    {
        return $wc_product->get_meta(StoreKeeperOptions::getBarcodeMetaKey($wc_product));
    }

    protected function getAttributeBoxFromContentVars(array $content_vars): array
    {
        $attributes = new Attributes($this->logger);
        $attribute_box = [];
        foreach ($content_vars as $cvData) {
            $contentVar = new Dot($cvData);

            if (!$contentVar->has('attribute_id')) {
                continue;
            }

            if (!$contentVar->get('attribute_published')) {
                continue;
            }

            $position = $contentVar->get('attribute_order', 0);
            if ($contentVar->has('attribute_option_id')) {
                $attribute_id = $attributes->importAttribute(
                    $contentVar->get('attribute_id'),
                    $contentVar->get('name'),
                    $contentVar->get('label')
                );
                $value = [
                    $attributes->importAttributeOption(
                        $attribute_id,
                        $contentVar->get('attribute_option_id'),
                        $contentVar->get('value'),
                        $contentVar->get('value_label'),
                        $contentVar->get('attribute_option_image_url', null),
                        $contentVar->get('attribute_option_order', 0),
                    ),
                ];
                $attribute = wc_get_attribute($attribute_id);
                $attribute_name = $attribute->slug;
            } else {
                $attribute_name = $contentVar->get('label');
                $value = (string) $contentVar->get('value');
            }

            $attribute_box[$attribute_name] = [
                self::ATTRIBUTE_NAMES => $attribute_name,
                self::ATTRIBUTE_VISIBILITY => $contentVar->get('attribute_published') ? 1 : 0,
                self::ATTRIBUTE_VALUES => $value,
                self::ATTRIBUTE_POSITION => $position,
            ];
        }

        return $attribute_box;
    }

    private static function prepareBoxData(array $attribute_box)
    {
        $attribute_data = array_fill_keys(self::ATTRIBUTE_KEYS, []);

        $i = 0;
        foreach ($attribute_box as $attribute) {
            ++$i;
            foreach (self::ATTRIBUTE_KEYS as $key) {
                if (array_key_exists($key, $attribute)) {
                    $attribute_data[$key][$i] = $attribute[$key];
                }
            }
        }

        return \WC_Meta_Box_Product_Data::prepare_attributes($attribute_data);
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

    public function setConfigurableAttributes(\WC_Product $newProduct, Dot $product, Dot $optionsConfig)
    {
        $attributes = new Attributes($this->logger);
        $attribute_sk_to_wc = $attributes->importsAttributes(
            $optionsConfig->get('attributes')
        );
        $options_sk_to_wc = $attributes->importsAttributeOptions(
            $attribute_sk_to_wc,
            $optionsConfig->get('attribute_options')
        );

        $attribute_box = $this->getAttributeBoxFromContentVars(
            $product->get('flat_product.content_vars', [])
        );

        $terms_per_sk_attribute = array_fill_keys(array_keys($attribute_sk_to_wc), []);
        foreach ($optionsConfig->get('attribute_options') as $option) {
            $terms_per_sk_attribute[$option['attribute_id']][] = $options_sk_to_wc[$option['id']];
        }

        $sk_attributes = self::getSortedAttributes($optionsConfig->get('attributes'));

        foreach ($sk_attributes as $sk_attribute) {
            $sk_attribute_id = $sk_attribute['id'];
            $attribute_id = $attribute_sk_to_wc[$sk_attribute_id];
            $attribute = wc_get_attribute($attribute_id);
            $attribute_box[$attribute->slug] = [
                self::ATTRIBUTE_NAMES => $attribute->slug,
                self::ATTRIBUTE_VALUES => $terms_per_sk_attribute[$sk_attribute_id],
                self::ATTRIBUTE_VISIBILITY => $sk_attribute['published'] ? 1 : 0,
                self::ATTRIBUTE_VARIATION => 1,
                self::ATTRIBUTE_POSITION => $sk_attribute['order'] ?? 0,
            ];
        }

        $data = self::prepareBoxData($attribute_box);
        $newProduct->set_attributes($data);

        ProductAttributes::setBarcodeMeta($newProduct, $product);
    }

    public function setAssignedAttributes(\WC_Product $newProduct, Dot $optionsConfig, array $wantedAttributeOptionIds): void
    {
        $attributes = new Attributes($this->logger);
        $attribute_sk_to_wc = $attributes->importsAttributes(
            $optionsConfig->get('attributes')
        );
        $options_sk_to_wc = $attributes->importsAttributeOptions(
            $attribute_sk_to_wc,
            $optionsConfig->get('attribute_options')
        );

        $options = [];
        foreach ($wantedAttributeOptionIds as $sk_attribute_id => $sk_option_id) {
            $attribute = wc_get_attribute($attribute_sk_to_wc[$sk_attribute_id]);
            $term = get_term($options_sk_to_wc[$sk_option_id]);
            $options[$attribute->slug] = $term->slug;
        }

        $newProduct->set_attributes($options);
    }

    public static function getSortedAttributes($attributes)
    {
        usort($attributes, function ($a, $b) {
            $ret = ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
            if (0 === $ret) {
                $ret = $a['label'] <=> $b['label'];
                if (0 === $ret) {
                    $ret = $a['id'] <=> $b['id'];
                }
            }

            return $ret;
        });

        return $attributes;
    }
}
