<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class TagDeleteTask extends AbstractTask
{
    /**
     * @throws WordpressException
     */
    public function run(array $task_options = []): void
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');

            $term = $this->getLabel($storekeeper_id);

            // Check if the term still exists.
            if (false !== $term) {
                wp_delete_term($term->term_id, 'product_tag');
            }
        }
    }

    /**
     * @return bool|\WP_Term
     *
     * @throws WordpressException
     */
    private function getLabel($StoreKeeperId)
    {
        $categories = WordpressExceptionThrower::throwExceptionOnWpError(
            get_terms(
                [
                    'taxonomy' => 'product_tag',
                    'hide_empty' => false,
                    'number' => 1,
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $StoreKeeperId,
                ]
            )
        );

        if (1 === count($categories)) {
            return $categories[0];
        }

        return false;
    }
}
