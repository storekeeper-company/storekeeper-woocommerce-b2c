<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

abstract class AbstractProductTask extends AbstractTask
{
    /**
     * @return bool|\wp_post
     *
     * @throws WordpressException
     */
    protected function getProduct($StoreKeeperId)
    {
        $products = WordpressExceptionThrower::throwExceptionOnWpError(
            get_posts(
                [
                    'post_type' => ['product', 'product_variation'],
                    'number' => 1,
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $StoreKeeperId,
                    'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
                ]
            )
        );

        if (1 === count($products)) {
            return $products[0];
        }

        return false;
    }

    protected function scheduleParentUpdate($parent_post_id)
    {
        // We plan a update of the parent post to make sure that one is updated
        $parentShopProductId = (int) get_post_meta($parent_post_id, 'storekeeper_id', true);

        // Check if the parent still exists.
        if ($parentShopProductId > 0) {
            $this->getTaskHandler()->rescheduleTask(
                TaskHandler::PARENT_PRODUCT_RECALCULATION,
                "shop_product_id::$parentShopProductId",
                [
                    'parent_shop_product_id' => $parentShopProductId,
                ]
            );
        }
    }
}
