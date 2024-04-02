<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Imports\CouponCodeImport;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class CouponCodeDeleteTask extends AbstractTask
{
    /**
     * @throws WordpressException
     */
    public function run(array $task_options = []): void
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');

            $coupon_code = $this->getCouponCode($storekeeper_id);

            // Check if the term still exists.
            if (!empty($coupon_code)) {
                wp_trash_post($coupon_code->ID);
            }
        }
    }

    /**
     * @return array
     *
     * @throws WordpressException
     */
    private function getCouponCode($storekeeper_id)
    {
        $coupon_codes = WordpressExceptionThrower::throwExceptionOnWpError(
            get_posts(
                [
                    'post_type' => CouponCodeImport::WC_POST_TYPE_COUPON_CODE,
                    'number' => 1,
                    'meta_key' => 'storekeeper_id',
                    'meta_value' => $storekeeper_id,
                    'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
                ]
            )
        );

        if (1 === count($coupon_codes)) {
            return $coupon_codes[0];
        } else {
            // none found
            return null;
        }
    }
}
