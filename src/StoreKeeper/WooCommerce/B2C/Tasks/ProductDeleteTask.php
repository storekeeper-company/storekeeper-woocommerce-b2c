<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

class ProductDeleteTask extends AbstractProductTask
{
    public function run(array $task_options = []): void
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');

            $post = $this->getProduct($storekeeper_id);

            // Check if the post exists.
            if (false !== $post) {
                // If we are dealing with an assigned product, we schedule and update for the parent
                if ('product_variation' === $post->post_type) {
                    $this->scheduleParentUpdate($post->post_parent);
                }

                // Properly deleting the product
                \WC()->product_factory->get_product($post->ID)->delete(true);
            }
        }
    }
}
