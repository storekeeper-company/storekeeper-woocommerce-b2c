<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

class MenuItemDeleteTask extends AbstractTask
{
    /**
     * @param $task_options array
     *
     * @return void returns true in the import was succeeded
     *
     * @throws \Exception
     */
    public function run(array $task_options = []): void
    {
        $this->debug('Deleting menu item', $this->getTaskMeta());

        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');

            $this->handleMenu($storekeeper_id);
        }

        $this->debug('Deleted menu item', $this->getTaskMeta());
    }

    /**
     * @param int $storekeeper_id menu id to remove
     *
     * @throws \Exception
     */
    protected function handleMenu($storekeeper_id)
    {
        // Search through the navigation menu items for the menu storekeeper id
        $posts = get_posts(
            [
                'post_type' => 'nav_menu_item',
                'meta_query' => [
                    [
                        'key' => 'menu_item_storekeeper_id',
                        'value' => strval($storekeeper_id),
                        'compare' => '=', // compare
                    ],
                ],
            ]
        );
        if (sizeof($posts) > 0) {
            $postId = $posts[0]->ID;
            wp_delete_post($postId);
        }
    }
}
