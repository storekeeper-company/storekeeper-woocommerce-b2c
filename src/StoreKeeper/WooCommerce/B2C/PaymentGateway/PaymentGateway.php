<?php

namespace StoreKeeper\WooCommerce\B2C\PaymentGateway;

use StoreKeeper\ApiWrapper\Exception\AuthException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\PaymentModel;
use StoreKeeper\WooCommerce\B2C\Models\RefundModel;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use StoreKeeper\WooCommerce\B2C\Tools\OrderHandler;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

class PaymentGateway
{
    const STATUS_CANCELED = 'CANCELED';

    const db_version = '1.1.0';
    const STOREKEEPER_PAY_ORDERS_PAYMENTS_TABLE = 'storekeeper_pay_orders_payments';
    const STOREKEEPER_PAY_DB_VERSION = 'storekeeper_pay_orders_payments_version';

    protected static function querySql(string $sql): bool
    {
        global $wpdb;

        if (false === $wpdb->query($sql)) {
            throw new \Exception($wpdb->last_error);
        }

        return true;
    }

    public static function getReturnUrl($order_id)
    {
        return add_query_arg(
            [
                'wc-api' => 'backoffice_pay_gateway_return',
                'utm_nooverride' => '1',
                'wc-order-id' => $order_id,
            ],
            home_url('/')
        );
    }

    public static function registerCheckoutFlash()
    {
        if (isset($_REQUEST['payment_status']) && self::STATUS_CANCELED == $_REQUEST['payment_status']) {
            add_action('woocommerce_before_checkout_form', [__CLASS__, 'displayFlashCanceled'], 20);
        }
        if (isset($_REQUEST['payment_error'])) {
            add_action('woocommerce_before_checkout_form', [__CLASS__, 'displayFlashError'], 20);
        }
    }

    public static function displayFlashCanceled()
    {
        wc_print_notice(__('The payment has been canceled, please try again', I18N::DOMAIN), 'error');
    }

    public static function displayFlashError()
    {
        $message = __('There was an error during processing of the payment: %s', I18N::DOMAIN);
        $message = sprintf($message, sanitize_text_field($_REQUEST['payment_error']));
        wc_print_notice($message, 'error');
    }

    /**
     * @param $order_id
     *
     * @return bool|null Returns null when the order is not found
     */
    public static function isPaymentSynced($order_id)
    {
        global $wpdb;

        $is_synced = null;
        $table_name = PaymentModel::getTableName();

        $sql = <<<SQL
SELECT is_synced
FROM `$table_name`
WHERE order_id = '$order_id'
LIMIT 1
SQL;

        // Getting the results and getting the first one.
        $results = $wpdb->get_results($sql, ARRAY_A);
        if (!empty($results)) {
            $is_synced = (bool) array_shift($results)['is_synced'];
        }

        return $is_synced;
    }

    /**
     * @param $order_id
     *
     * @return bool
     */
    public static function hasPayment($order_id)
    {
        return (bool) self::getPaymentId($order_id);
    }

    /**
     * @param $order_id
     *
     * @return bool whenever the payment update was success or not
     */
    public static function markPaymentAsSynced($order_id)
    {
        global $wpdb;

        return false !== $wpdb->update(
                PaymentModel::getTableName(), // table
                ['is_synced' => true], // data
                ['order_id' => $order_id], // where
                ['%d'], // data format
                ['%d'] // where format
            );
    }

    /**
     * @param $order_id
     * @param $payment_id
     *
     * @return bool whenever the payment update was success or not
     */
    public static function updatePayment($order_id, $payment_id)
    {
        global $wpdb;

        return false !== $wpdb->update(
            // table
                PaymentModel::getTableName(),
                // data
                [
                    'payment_id' => $payment_id,
                    'is_synced' => false, // Update un sets the payment sync status.
                ],
                // where
                ['order_id' => $order_id],
                // data format
                [
                    '%d',
                    '%d',
                ],
                    // where format
                ['%d']
            );
    }

    /**
     * @param $order_id
     * @param $payment_id
     *
     * @return bool
     */
    public static function addPayment($order_id, $payment_id)
    {
        global $wpdb;

        return false !== $wpdb->insert(
            // table
                PaymentModel::getTableName(),
                // data
                [
                    'order_id' => $order_id,
                    'payment_id' => $payment_id,
                ],
                // format
                [
                    '%d',
                    '%d',
                ]
            );
    }

    /**
     * @param $orderId
     * @param $paymentId
     * @param $refundId
     * @param $amount
     *
     * @return bool
     */
    public static function addRefund($orderId, $paymentId, $refundId, $amount)
    {
        global $wpdb;

        return false !== $wpdb->insert(
            // table
                RefundModel::getTableName(),
                // data
                [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'refund_id' => $refundId,
                    'amount' => $amount,
                ],
                // format
                [
                    '%d',
                    '%d',
                    '%d',
                    '%d',
                ]
            );
    }

    public function onReturn()
    {
        global $woocommerce;
        $url = $woocommerce->cart->get_checkout_url();

        try {
            // Getting the WC order
            $order = new \WC_Order(sanitize_key($_GET['wc-order-id']));
            $payment_id = self::getPaymentId($order->get_id());

            // Check payment in the backend
            $api = StoreKeeperApi::getApiByAuthName();
            $shop_module = $api->getModule('ShopModule');
            $payment = $shop_module->syncWebShopPaymentWithReturn($payment_id);
            $payment_status = $payment['status'];

            // Make a note with the received order payment status
            $statusText = __('The order\'s payment status received: %s', I18N::DOMAIN);
            $order->add_order_note(sprintf($statusText, $payment_status));

            // Check if the payment was paid
            if (in_array($payment_status, ['paid', 'authorized'], true)) {
                $url = self::getOrderReturnUrl($order);

                // Payment done, mark order as completed
                $order->set_status(StoreKeeperBaseGateway::STATUS_PROCESSING);
            } else {
                $url = add_query_arg('payment_status', self::STATUS_CANCELED, $url);
            }

            $order->save();
        } catch (\Throwable $exception) {
            // Log error
            LoggerFactory::create('checkout')->error($exception->getMessage(), $exception->getTrace());
            LoggerFactory::createErrorTask('payment-error', $exception);

            // Update url
            $url = add_query_arg('payment_error', urlencode($exception->getMessage()), $url);
        }

        wp_redirect($url);
    }

    public function createRefundPayment($orderId, $refundId)
    {
        $refund = wc_get_order($refundId);
        $refundAmount = $refund->get_total();

        $api = StoreKeeperApi::getApiByAuthName();
        $paymentModule = $api->getModule('PaymentModule');
        try {
            if (!self::refundExists($orderId, $refundId)) {
                $paymentId = $paymentModule->newWebPayment([
                    'amount' => -abs($refundAmount), // Refund should be negative
                    'description' => sprintf(
                        __('Refund via Wordpress plugin (Refund #%s)', I18N::DOMAIN),
                        $refundId
                    ),
                ]);
                self::addRefund($orderId, $paymentId, $refundId, $refundAmount);

                $orderHandler = new OrderHandler();
                $task = $orderHandler->create($orderId);

                if (!$task) {
                    throw new \Exception('Order export task was not created');
                }
            }
        } catch (\Throwable $exception) {
            LoggerFactory::create('refund')->error($exception->getMessage(), $exception->getTrace());
        }
    }

    public static function hasUnsyncedRefunds($orderId): bool
    {
        return count(self::getUnsyncedRefundsPaymentIds($orderId)) > 0;
    }

    public static function getUnsyncedRefundsPaymentIds($orderId): array
    {
        global $wpdb;

        $table_name = RefundModel::getTableName();

        $sql = <<<SQL
SELECT payment_id, refund_id
FROM `$table_name`
WHERE order_id = '$orderId'
AND is_synced = false
SQL;

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * @param $orderId
     * @param $paymentId
     * @param $refundId
     *
     * @return bool whenever the payment update was success or not
     */
    public static function markRefundAsSynced($orderId, $paymentId, $refundId): bool
    {
        global $wpdb;

        return false !== $wpdb->update(
                RefundModel::getTableName(), // table
                ['is_synced' => true], // data
                [
                    'order_id' => $orderId,
                    'payment_id' => $paymentId,
                    'refund_id' => $refundId,
                ] // where
            );
    }

    /**
     * @param $orderId
     * @param $refundId
     *
     * @return bool
     */
    public static function refundExists($orderId, $refundId): ?bool
    {
        global $wpdb;

        $payment_id = null;
        $table_name = RefundModel::getTableName();

        $sql = <<<SQL
SELECT payment_id
FROM `$table_name`
WHERE order_id = '$orderId'
AND refund_id = '$refundId'
LIMIT 1
SQL;

        // Getting the results and getting the first one.
        $results = $wpdb->get_results($sql, ARRAY_A);
        if (!empty($results)) {
            $payment_id = array_shift($results)['payment_id'];
        }

        return $payment_id;
    }

    public static function getPaymentId($order_id)
    {
        global $wpdb;

        // Pay NL
        $payment_id = null;
        $table_name = PaymentModel::getTableName();

        $sql = <<<SQL
SELECT payment_id
FROM `$table_name`
WHERE order_id = '$order_id'
LIMIT 1
SQL;

        // Getting the results and getting the first one.
        $results = $wpdb->get_results($sql, ARRAY_A);
        if (!empty($results)) {
            $payment_id = array_shift($results)['payment_id'];
        }

        return $payment_id;
    }

    public static function getOrderReturnUrl(\WC_Order $order)
    {
        // return url return
        $return_url = $order->get_checkout_order_received_url();
        if (is_ssl() || 'yes' == get_option('woocommerce_force_ssl_checkout')) {
            $return_url = str_replace('http:', 'https:', $return_url);
        }

        return apply_filters('woocommerce_get_return_url', $return_url, $order);
    }

    /**
     * Get backend payment id by woocommerce order id.
     *
     * @param $order_id
     *
     * @throws \Exception
     */
    public function checkPayment($order_id)
    {
        $order = new \WC_Order($order_id);

        // Check if the order was not marked as completed yet.
        if (StoreKeeperBaseGateway::STATUS_PROCESSING !== $order->get_status()) {
            //old orders may not have order_id and payment_id linked or orders that didn't use the Payment Gateway
            $payment_id = self::getPaymentId($order_id);
            if ($payment_id) {
                $api = StoreKeeperApi::getApiByAuthName();
                $shop_module = $api->getModule('ShopModule');
                $payment = $shop_module->syncWebShopPaymentWithReturn($payment_id);

                if (in_array($payment['status'], ['paid', 'authorized'], true)) {
                    //payment in backend is marked as paid
                    $order->set_status(StoreKeeperBaseGateway::STATUS_PROCESSING);
                    $order->save();
                }
            }
        }
    }

    public function addGatewayClasses($default_gateway_classes)
    {
        try {
            $api = StoreKeeperApi::getApiByAuthName();
            $ShopModule = $api->getModule('ShopModule');

            $methods = $ShopModule->listTranslatedPaymentMethodForHooks(
                Language::getSiteLanguageIso2(),
                0,
                0,
                null,
                [
                    [
                        // Only show web compatible payment methods
                        'name' => 'provider_method_type/alias__in_list',
                        'multi_val' => ['Web', 'ExternalGiftCard', 'OnlineGiftCard'],
                    ],
                ]
            )['data'];

            $gateway_classes = [];
            foreach ($methods as $method) {
                $imageUrl = array_key_exists('image_url', $method) ? $method['image_url'] : '';
                $gateway = new StoreKeeperBaseGateway(
                    "sk_pay_id_{$method['id']}", $method['title'], (int) $method['id'],
                    $imageUrl
                );
                $gateway_classes[] = $gateway;

                //force enable it (the method's here are always available)
                update_option(
                    'woocommerce_'.$gateway->getId().'_settings',
                    [
                        'enabled' => 'yes',
                    ]
                );
            }
        } catch (AuthException $authException) {
            LoggerFactory::create('checkout')->error($authException->getMessage(), $authException->getTrace());
            LoggerFactory::createErrorTask('add-storeKeeper-gateway-auth', $authException);

            return $default_gateway_classes;
        }

        return array_merge($default_gateway_classes, $gateway_classes);
    }
}
