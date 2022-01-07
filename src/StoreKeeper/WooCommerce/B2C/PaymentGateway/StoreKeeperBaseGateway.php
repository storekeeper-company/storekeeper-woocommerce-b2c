<?php

namespace StoreKeeper\WooCommerce\B2C\PaymentGateway;

use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\CustomerFinder;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

class StoreKeeperBaseGateway extends \WC_Payment_Gateway
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_ON_HOLD = 'on-hold';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_FAILED = 'failed';
    private $provider_method_id;

    public function __construct(string $id, string $title, int $provider_method_id, string $icon_url)
    {
        $this->id = $id;
        $this->provider_method_id = $provider_method_id;
        $this->setIconUrl($icon_url);
        $this->title = $title;

        $this->has_fields = false;
        $this->description = null;
        $this->method_title = 'StoreKeeper - '.$title;

        $this->init_form_fields();
        $this->init_settings();
    }

    /**
     * @throws \Exception
     */
    public function getId()
    {
        return $this->id;
    }

    public function process_payment($order_id)
    {
        $order = new \WC_Order($order_id);

        try {
            return [
                'result' => 'success',
                'redirect' => $this->getPaymentUrl($order),
            ];
        } catch (\Throwable $exception) {
            LoggerFactory::create('checkout')->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
            LoggerFactory::createErrorTask('process-payment', $exception);
        }

        return [];
    }

    private function getPaymentUrl(\WC_Order $order)
    {
        $api = StoreKeeperApi::getApiByAuthName();
        $shop_module = $api->getModule('ShopModule');

        $relation_data_id = null;
        $relation_data_snapshot_id = null;
        try {
            $relation_data_id = CustomerFinder::ensureCustomerFromOrder($order);
        } catch (GeneralException $exception) {
            // Unable to create the customer.
            // this can be due to the customer having a admin account in the backoffice.
            LoggerFactory::createErrorTask('order-payment-url', $exception, $order->get_id(), $order->get_data());
        }

        // Create payment
        $payment = $shop_module->newWebShopPaymentWithReturn(
            [
                'redirect_url' => PaymentGateway::getReturnUrl($order->get_id()),
                'provider_method_id' => $this->provider_method_id,
                'amount' => $order->get_total(),
                'title' => __('Order number', I18N::DOMAIN).': '.$order->get_order_number(),
                'relation_data_id' => $relation_data_id,
                'relation_data_snapshot' => CustomerFinder::extractSnapshotDataFromOrder($order),
                'end_user_ip' => $this->getUserIp(),
            ]
        );

        //we insert the order id with payment id here so on the detail page later on we can check
        //whether or not the order is paid
        if (PaymentGateway::hasPayment($order->get_id())) {
            $update = PaymentGateway::updatePayment($order->get_id(), $payment['id']);
        } else {
            $update = PaymentGateway::addPayment($order->get_id(), $payment['id']);
        }

        if (!$update) {
            throw new \Exception('Not able to add or update payment');
        }

        // Make a note about the current payment after creation
        $payment_eid = in_array('eid', $payment) ? $payment['eid'] : '-';
        $payment_trx = $payment['trx'];
        $status_text = __("The order's payment was created\n eid: %s\n trx: %s", I18N::DOMAIN);
        $order->add_order_note(sprintf($status_text, $payment_eid, $payment_trx));

        // Mark order as pending AKA waiting for payment.
        $order->set_status(self::STATUS_PENDING);
        $order->save();

        return $payment['payment_url'];
    }

    /**
     * @return mixed
     */
    private function getUserIp()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * @param string $icon_url
     */
    protected function setIconUrl(?string $icon_url): void
    {
        $this->icon = StoreKeeperApi::getResourceUrl($icon_url);
    }
}
