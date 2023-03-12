<?php

namespace StoreKeeper\WooCommerce\B2C\PaymentGateway;

use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\CustomerFinder;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use WC_Order_Item_Product;
use WC_Product_Factory;

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

    /**
     * @var \Throwable|null
     */
    private $lastError = null;

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

            $this->lastError = $exception;
        }

        return [];
    }

    public function getLastError(): ?\Throwable
    {
        return $this->lastError;
    }

    /**
     * @param \Throwable|null $lastError
     */
    public function clearLastError(): void
    {
        $this->lastError = null;
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

        $products = [];
        $productFactory = new WC_Product_Factory();

        /* @var WC_Order_Item_Product $orderProduct */
        foreach ($order->get_items() as $index => $orderProduct) {
            $variationProductId = $orderProduct->get_variation_id();
            $isVariation = $variationProductId > 0; // Variation_id is 0 by default, if it is any other, it's a variation product;

            if ($isVariation) {
                $productId = $variationProductId;
            } else {
                $productId = $orderProduct->get_product_id();
            }
            $currentProduct = $productFactory->get_product($productId);

            $data = [
                'sku' => $currentProduct ? $currentProduct->get_sku(OrderExport::CONTEXT) : $orderProduct->get_name(
                    OrderExport::CONTEXT
                ),
                'name' => $currentProduct ? $currentProduct->get_name(OrderExport::CONTEXT) : $orderProduct->get_name(
                    OrderExport::CONTEXT
                ),
                'ppu_wt' => $order->get_item_total($orderProduct, true, false),
                'quantity' => $orderProduct->get_quantity(OrderExport::CONTEXT),
                'is_shipping' => 'false',
                'is_payment' => 'false',
                'is_discount' => 'false',
            ];

            $products[] = $data;
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
                'products' => $products,
            ]
        );

        //we insert the order id with payment id here so on the detail page later on we can check
        //whether or not the order is paid
        if (PaymentGateway::hasPayment($order->get_id())) {
            PaymentGateway::updatePayment($order->get_id(), $payment['id'], $order->get_total());
        } else {
            PaymentGateway::addPayment($order->get_id(), $payment['id'], $order->get_total());
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
