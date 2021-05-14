<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

/**
 * This task should do EXACTLY the same as deletion.
 *
 * Class ProductDeactivateTask
 */
class ProductDeactivateTask extends ProductDeleteTask
{
    public function run($task_options = [])
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

                // Move the post to the trash
                wp_trash_post($post->ID);
            }
        }

        return true;
    }
}
