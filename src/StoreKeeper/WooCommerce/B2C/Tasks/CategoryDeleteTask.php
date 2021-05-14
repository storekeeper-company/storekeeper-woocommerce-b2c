<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Tools\Categories;

class CategoryDeleteTask extends AbstractTask
{
    /**
     * @param $task_options
     *
     * @return bool
     *
     * @throws WordpressException
     */
    public function run($task_options = [])
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');

            /**
             * @var \WC_Product_Attribute $attribute
             */
            $term = Categories::getCategoryById($storekeeper_id);

            // Check if the term still exists.
            if (false !== $term) {
                Categories::deleteCategoryByTermId($term->term_id);
            }
        }

        return true;
    }
}
