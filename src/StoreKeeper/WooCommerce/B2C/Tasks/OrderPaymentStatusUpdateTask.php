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

                if ($wcOrder && 'pending' === $wcOrder->get_status()) {
                    $wcOrder->add_order_note(
                        sprintf(
                            __('StoreKeeper: Order was automatically cancelled due to payment expiration (Payment ID=%s)'),
                            $paymentData['trx'] ?? $paymentData['id']
                        ), true);
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
        $args = [
            'meta_key' => 'storekeeper_id',
            'meta_value' => $storekeeper_id,
        ];
        $orders = wc_get_orders($args);

        if (count($orders) > 1) {
            throw new \RuntimeException('More than one order found for storekeeper id '.$storekeeper_id);
        }

        if (0 === count($orders)) {
            return false;
        }

        return current($orders);
    }
}
