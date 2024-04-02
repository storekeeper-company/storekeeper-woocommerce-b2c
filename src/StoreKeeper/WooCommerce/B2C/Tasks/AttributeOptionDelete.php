<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Tools\StringFunctions;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class AttributeOptionDelete extends AbstractTask
{
    public function run(array $task_options = []): void
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');

            $BlogModule = $this->storekeeper_api->getModule('BlogModule');
            $response = $BlogModule->listTranslatedAttributes(
                0,
                0,
                1,
                [
                    [
                        'name' => 'id',
                        'dir' => 'desc',
                    ],
                ],
                [
                    [
                        'name' => 'id__=',
                        'val' => $storekeeper_id,
                    ],
                ]
            );

            $data = $response['data'][0];
            if (1 === $response['count'] && $data) {
                $attr_slug = $data['attribute']['name'];
                $attr_name = $data['attribute']['label'];
                $attr_op_term = $this->getAttributeOption($attr_slug, $attr_name, $storekeeper_id);
                if (false !== $attr_op_term) {
                    wp_delete_term($attr_op_term->term_id, self::get_wc_attribute_tax_name($attr_slug));
                }
            }
        }
    }

    /**
     * @throws WordpressException
     */
    private function registerAttribute($raw_attribute_slug, $raw_attribute_name)
    {
        // Register as taxonomy while importing.
        $taxonomy_name = self::get_wc_attribute_tax_name($raw_attribute_slug);
        WordpressExceptionThrower::throwExceptionOnWpError(
            register_taxonomy(
                $taxonomy_name,
                apply_filters('woocommerce_taxonomy_objects_'.$taxonomy_name, ['product']),
                apply_filters(
                    'woocommerce_taxonomy_args_'.$taxonomy_name,
                    [
                        'labels' => [
                            'name' => $raw_attribute_name,
                        ],
                        'hierarchical' => true,
                        'show_ui' => false,
                        'query_var' => true,
                        'rewrite' => false,
                    ]
                )
            )
        );
    }

    /**
     * @return bool|\WP_Term
     *
     * @throws WordpressException
     */
    private function getAttributeOption($taxonomy_slug, $taxonomy_name, $StoreKeeperId)
    {
        $this->registerAttribute($taxonomy_slug, $taxonomy_name);
        $labels = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => self::get_wc_attribute_tax_name($taxonomy_slug),
                    'hide_empty' => false,
                    'number' => 1,
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $StoreKeeperId,
                ]
            )
        );

        if (1 === count($labels)) {
            return $labels[0];
        }

        return false;
    }

    private function get_wc_attribute_tax_name($tax_name)
    {
        if (strlen($tax_name) > 32) {
            $tax_name = substr($tax_name, 0, 31);
        }
        if (!StringFunctions::startsWith($tax_name, 'pa_')) {
            $tax_name = wc_attribute_taxonomy_name($tax_name);
        }

        return $tax_name;
    }
}
