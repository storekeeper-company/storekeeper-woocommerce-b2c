<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

class OrderPaymentStatusUpdateTask extends AbstractTask
{
    public function run(array $task_options = []): void
    {
        if (
            $this->taskMetaExists('storekeeper_id')
            && $this->taskMetaExists('order')
            && $this->taskMetaExists('payment')
        ) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');
            $payment = $this->getTaskMeta('payment');

            $paymentData = json_decode($payment, true, 512, JSON_THROW_ON_ERROR);

            if ('expired' === $paymentData['status']) {
                $wcOrder = $this->getOrderByStoreKeeperId($storekeeper_id);
                if ($wcOrder && 'cancelled' !== $wcOrder->get_status()) {
                    // This will trigger OrderHandler::updateWithIgnore already so it will create an order export task
                    $wcOrder->set_status('cancelled');
                    $wcOrder->save();
                }
            }
        }
    }

    /**
     * @return false|\WC_Order
     */
    protected function getOrderByStoreKeeperId($storekeeper_id)
    {
        global $wpdb;

        $sql = <<<SQL
SELECT ID FROM {$wpdb->prefix}posts
INNER JOIN {$wpdb->prefix}postmeta
ON {$wpdb->prefix}posts.ID={$wpdb->prefix}postmeta.post_id
WHERE {$wpdb->prefix}postmeta.meta_key='storekeeper_id'
AND {$wpdb->prefix}postmeta.meta_value=%d
AND {$wpdb->prefix}posts.post_type='shop_order'
SQL;

        $safe_sql = $wpdb->prepare($sql, $storekeeper_id);

        $response = $wpdb->get_row($safe_sql);

        if (isset($response->ID)) {
            return new \WC_Order($response->ID);
        }

        return false;
    }
}
