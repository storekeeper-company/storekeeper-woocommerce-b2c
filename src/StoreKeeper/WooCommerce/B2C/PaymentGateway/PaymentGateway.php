<?php

namespace StoreKeeper\WooCommerce\B2C\PaymentGateway;

use Monolog\Logger;
use StoreKeeper\ApiWrapper\Exception\AuthException;
use StoreKeeper\WooCommerce\B2C\Exceptions\PaymentException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\PaymentModel;
use StoreKeeper\WooCommerce\B2C\Models\RefundModel;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use StoreKeeper\WooCommerce\B2C\Tools\OrderHandler;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

class PaymentGateway
{
    public const FLASH_QUERY_ARG = 'payment_status';
    public const FLASH_STATUS_CANCELLED = 'CANCELED';
    public const FLASH_STATUS_ON_HOLD = 'ONHOLD';
    public const FLASH_STATUS_PENDING = 'PENDING';
    const PAYMENT_PENDING_STATUSES = ['open', 'authorized', 'verify'];
    const PAYMENT_PAID_STATUSES = ['paid', 'paidout',  'refunded', 'refunding', 'partial_refund'];
    const PAYMENT_CANCELLED_STATUSES = ['cancelled', 'expired', 'error'];
    public static $refundedBySkStatus = false;

    protected static function querySql(string $sql): bool
    {
        global $wpdb;

        if (false === $wpdb->query($sql)) {
            throw new \Exception($wpdb->last_error);
        }

        return true;
    }

    /**
     * @hook $this->loader->add_filter('init', $PaymentGateway, 'registerCheckoutFlash');
     */
    public static function registerCheckoutFlash()
    {
        if (isset($_REQUEST[PaymentGateway::FLASH_QUERY_ARG]) && self::FLASH_STATUS_CANCELLED == $_REQUEST[PaymentGateway::FLASH_QUERY_ARG]) {
            add_action('woocommerce_before_checkout_form', [__CLASS__, 'displayFlashCanceled'], 20);
        }
        if (isset($_REQUEST[PaymentGateway::FLASH_QUERY_ARG]) && self::FLASH_STATUS_PENDING == $_REQUEST[PaymentGateway::FLASH_QUERY_ARG]) {
            add_action('woocommerce_thankyou_order_received_text', [__CLASS__, 'displayFlashPending'], 20);
        }
        if (isset($_REQUEST[PaymentGateway::FLASH_QUERY_ARG]) && self::FLASH_STATUS_ON_HOLD == $_REQUEST[PaymentGateway::FLASH_QUERY_ARG]) {
            add_action('woocommerce_thankyou_order_received_text', [__CLASS__, 'displayFlashPending'], 20);
        }
        if (isset($_REQUEST['payment_error'])) {
            add_action('woocommerce_before_checkout_form', [__CLASS__, 'displayFlashError'], 20);
        }
    }

    public static function displayFlashCanceled()
    {
        wc_print_notice(__('The payment has been canceled, please try again', I18N::DOMAIN), 'error');
    }

    public static function displayFlashPending()
    {
        wc_print_notice(__('Your order is awaiting payment. Once we receive it, we\'ll process your purchase.', I18N::DOMAIN), 'notice');
    }

    public static function displayFlashError()
    {
        $message = __('There was an error during processing of the payment: %s', I18N::DOMAIN);
        $message = sprintf($message, sanitize_text_field($_REQUEST['payment_error']));
        wc_print_notice($message, 'error');
    }

    /**
     * @param $orderId
     * @param $skRefundId
     * @param $refundId
     * @param $amount
     *
     * @return bool
     */
    public static function addRefund($orderId, $skRefundId, $refundId, $amount)
    {
        global $wpdb;

        return false !== $wpdb->insert(
            // table
                RefundModel::getTableName(),
                // data
                [
                    'wc_order_id' => $orderId,
                    'sk_refund_id' => $skRefundId,
                    'wc_refund_id' => $refundId,
                    'amount' => $amount,
                ],
                // format
                [
                    '%d',
                    '%d',
                    '%d',
                    '%s',
                ]
            );
    }

    public static function updateRefund($id, $skRefundId, $amount, $isSynced = false)
    {
        global $wpdb;

        return false !== $wpdb->update(
            // table
                RefundModel::getTableName(),
                // data
                [
                    'sk_refund_id' => $skRefundId,
                    'is_synced' => $isSynced,
                    'amount' => $amount,
                ],
                // where
                ['id' => $id],
                // data format
                [
                    '%d',
                    '%d',
                    '%s',
                ],
                // where format
                ['%d']
            );
    }

    /**
     * @hook $this->loader->add_filter('woocommerce_api_backoffice_pay_gateway_return', $PaymentGateway, 'onReturn');
     */
    public function onReturn()
    {
        $log_context = [
            'get_params' => $_GET,
        ];
        $checkOutLogger = $this->getCheckOutLogger();
        $checkOutLogger->debug('Loading checkout page', $log_context);
        try {
            // Getting the WC order
            $order = new \WC_Order(sanitize_key($_GET['wc-order-id']));
            $trx = sanitize_key($_GET['trx']);
            if (empty($trx)) {
                $statusText = __('Invalid return url, contact shop owner to check the payment', I18N::DOMAIN);
                throw new PaymentException($statusText);
            }
            $payment_id = PaymentModel::getPaymentIdByTrx($trx);

            $log_context += [
                'order_id' => $order->get_id(),
                'payment_id' => $payment_id,
            ];
            // Check payment in the backend
            $api = StoreKeeperApi::getApiByAuthName();
            $shop_module = $api->getModule('ShopModule');
            $payment = $shop_module->syncWebShopPaymentWithReturn($payment_id);
            $payment_status = $payment['status'];

            $log_context += [
                PaymentGateway::FLASH_QUERY_ARG => $payment_status,
            ];
            // Make a note with the received order payment status
            $statusText = __("The order\'s payment status received: %s\ntrx=%s", I18N::DOMAIN);
            $order->add_order_note(sprintf($statusText, $payment_status, $trx));

            if ($this->isPaymentStatusPaid($payment_status)) {
                $checkOutLogger->debug('Payment paid', $log_context);
                $order->set_status(StoreKeeperBaseGateway::ORDER_STATUS_PROCESSING);
                PaymentModel::markPaymentIdAsPaid($payment_id);
            } elseif (in_array($payment_status, self::PAYMENT_PENDING_STATUSES, true)) {
                $checkOutLogger->debug('Payment pending', $log_context);
                $order->set_status(StoreKeeperBaseGateway::ORDER_STATUS_PENDING);
            } elseif (in_array($payment_status, self::PAYMENT_CANCELLED_STATUSES, true)) {
                $checkOutLogger->debug('Payment canceled, redirecting back to checkout', $log_context);
            } else {
                $checkOutLogger->warning('Unknown payment status', $log_context);
                $order->set_status(StoreKeeperBaseGateway::ORDER_STATUS_ON_HOLD);
            }

            $order->save();

            $url = $this->getFinalPaymentPageUrl($payment_status, $order);
        } catch (\Throwable $exception) {
            // Log error
            $checkOutLogger->error($exception->getMessage(), $log_context);
            LoggerFactory::createErrorTask('payment-error', $exception, $log_context);

            // Update url
            $url = wc_get_checkout_url();
            $url = add_query_arg('payment_error', urlencode($exception->getMessage()), $url);
        }

        $checkOutLogger->debug('Redirect to final page', $log_context + ['url' => $url]);
        wp_redirect($url);
    }

    /**
     * @param $args
     *
     * @return void
     *
     * @throws \Exception
     * @hook $this->loader->add_action('woocommerce_create_refund', $PaymentGateway, 'createWooCommerceRefund', 10, 2);
     */
    public function createWooCommerceRefund(\WC_Order_Refund $refund, $args)
    {
        $orderId = $args['order_id'];
        $isRefundCreationAllowed = true;
        $storeKeeperOrderId = get_post_meta($orderId, 'storekeeper_id', true);

        if ($storeKeeperOrderId) {
            // Refunded by storekeeper means it's just forced to be refunded because of BackOffice status
            if (self::$refundedBySkStatus) {
                $isRefundCreationAllowed = false;
                LoggerFactory::create('refund')->error('Refund is dirty', ['order_id' => $orderId, 'storekeeper_id' => $storeKeeperOrderId]);
            } else {
                $api = StoreKeeperApi::getApiByAuthName();
                $shopModule = $api->getModule('ShopModule');

                $order = $shopModule->getOrder($storeKeeperOrderId, null);

                $isRefundCreationAllowed = $this->checkRefundCreationAllowed($order, (float) $refund->get_amount(), $orderId, $storeKeeperOrderId);
            }
        }

        // This will prevent creation of refund
        if (!$isRefundCreationAllowed) {
            throw new \RuntimeException('Refund is not allowed to be created due to certain conditions');
        }
    }

    /**
     * @param $orderId
     * @param $refundId
     *
     * @throws \Exception
     * @hook $this->loader->add_action('woocommerce_order_refunded', $PaymentGateway, 'createStoreKeeperRefundPayment', 10, 2);
     * @hook $this->loader->add_action('woocommerce_order_partially_refunded', $PaymentGateway, 'createStoreKeeperRefundPayment', 10, 2);
     */
    public function createStoreKeeperRefundPayment($orderId, $refundId): void
    {
        $refund = wc_get_order($refundId);
        $refundAmount = $refund->get_amount();

        if (!self::refundExists($orderId, $refundId)) {
            self::addRefund($orderId, null, $refundId, $refundAmount);
        }

        $orderHandler = new OrderHandler();
        $task = $orderHandler->create($orderId);

        if (!$task) {
            throw new \Exception('Order export task was not created');
        }
    }

    public static function createRefundAsPayment($refundId, $refundAmount)
    {
        $api = StoreKeeperApi::getApiByAuthName();
        $paymentModule = $api->getModule('PaymentModule');

        return $paymentModule->newWebPayment([
            'amount' => round(-abs($refundAmount), 2), // Refund should be negative
            'description' => sprintf(
                __('Refund via Wordpress plugin (Refund #%s)', I18N::DOMAIN),
                $refundId
            ),
        ]);
    }

    public static function hasUnsyncedRefunds($orderId): bool
    {
        return count(self::getUnsyncedRefundsPaymentIds($orderId)) > 0;
    }

    /**
     * All refunds that have Storekeeper refund ID but is not synced yet.
     *
     * @param $orderId
     */
    public static function getUnsyncedRefundsPaymentIds($orderId): array
    {
        global $wpdb;

        $table_name = RefundModel::getTableName();

        $sql = <<<SQL
SELECT sk_refund_id, wc_refund_id, amount
FROM `$table_name`
WHERE wc_order_id = '$orderId'
AND sk_refund_id IS NOT NULL
AND is_synced = false
SQL;

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Refunds that are not totally synced yet, and has no Storekeeper refund ID.
     *
     * @param $orderId
     */
    public static function getUnsyncedRefundsWithoutPaymentIds($orderId): array
    {
        global $wpdb;

        $table_name = RefundModel::getTableName();

        $sql = <<<SQL
SELECT id, wc_refund_id, amount
FROM `$table_name`
WHERE wc_order_id = '$orderId'
AND is_synced = false
AND sk_refund_id IS NULL
SQL;

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * @param $orderId
     * @param $skRefundId
     * @param $refundId
     *
     * @return bool whenever the payment update was success or not
     */
    public static function markRefundAsSynced($orderId, $skRefundId, $refundId): bool
    {
        global $wpdb;

        return false !== $wpdb->update(
                RefundModel::getTableName(), // table
                ['is_synced' => true], // data
                [
                    'wc_order_id' => $orderId,
                    'sk_refund_id' => $skRefundId,
                    'wc_refund_id' => $refundId,
                ] // where
            );
    }

    /**
     * @param $orderId
     * @param $refundId
     */
    public static function refundExists($orderId, $refundId): ?int
    {
        global $wpdb;

        $table_name = RefundModel::getTableName();

        $sql = <<<SQL
SELECT sk_refund_id, is_synced
FROM `$table_name`
WHERE wc_order_id = '$orderId'
AND wc_refund_id = '$refundId'
LIMIT 1
SQL;

        // Getting the results and getting the first one.
        $results = $wpdb->get_results($sql, ARRAY_A);

        if (empty($results)) {
            return false;
        }

        $refund = array_shift($results);
        $storeKeeperRefundId = $refund['sk_refund_id'];
        $isSynchronized = $refund['is_synced'];

        // If no refund ID yet but already marked as synchronized, otherwise true
        return !(is_null($storeKeeperRefundId) && $isSynchronized);
    }

    /**
     * Get backend payment id by woocommerce order id.
     *
     * @param $order_id
     * @hook $this->loader->add_action('woocommerce_thankyou', $PaymentGateway, 'checkPayment');
     *
     * @throws \Exception
     */
    public function checkPayment($order_id)
    {
        $order = new \WC_Order($order_id);

        // Check if the order was not marked as completed yet.
        if (StoreKeeperBaseGateway::ORDER_STATUS_PROCESSING !== $order->get_status()) {
            //old orders may not have order_id and payment_id linked or orders that didn't use the Payment Gateway
            $payments = PaymentModel::findOrderPayments($order_id);
            if ($payments) {
                $api = StoreKeeperApi::getApiByAuthName();
                $shop_module = $api->getModule('ShopModule');
                foreach ($payments as $payment) {
                    $is_paid = !empty($payment['is_paid']);
                    $payment_id = $payment['payment_id'];
                    if (!$is_paid) {
                        $payment = $shop_module->syncWebShopPaymentWithReturn($payment_id);
                        $is_paid = $this->isPaymentStatusPaid($payment['status']);
                    }

                    if ($is_paid) {
                        //payment in backend is marked as paid
                        PaymentModel::markPaymentIdAsPaid($payment_id);
                        $order->set_status(StoreKeeperBaseGateway::ORDER_STATUS_PROCESSING);
                        $order->save();
                    }
                }
            }
        }
    }

    /**
     * @hook $this->loader->add_filter('woocommerce_payment_gateways', $PaymentGateway, 'addGatewayClasses');
     *
     * @throws \StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException
     */
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
            $this->getCheckOutLogger()->error($authException->getMessage(), ['trace' => $authException->getTraceAsString()]);
            LoggerFactory::createErrorTask('add-storeKeeper-gateway-auth', $authException);

            return $default_gateway_classes;
        }

        return array_merge($default_gateway_classes, $gateway_classes);
    }

    protected function checkRefundCreationAllowed(array $order, float $refundAmount, $orderId, $storeKeeperOrderId): bool
    {
        if ($this->storekeeperOrderHasRefundWithReturnPayment($order)) {
            $paidValue = $order['paid_value_wt'];
            $refundedValue = $order['paid_back_value_wt'];
            $maxRefundValue = $paidValue - $refundedValue;
            $floatEpsilon = 0.00001;
            // Epsilon is only defined for PHP 7.2 and above
            if (defined('PHP_FLOAT_EPSILON')) {
                $floatEpsilon = PHP_FLOAT_EPSILON;
            }

            if (abs($refundAmount - $maxRefundValue) < $floatEpsilon) {
                LoggerFactory::create('refund')->error('Order has refund on BackOffice already and refund is more than the expected amount', ['order_id' => $orderId, 'storekeeper_id' => $storeKeeperOrderId]);

                return false;
            }
        }

        return true;
    }

    protected function storekeeperOrderHasRefundWithReturnPayment(array $order): bool
    {
        $hasRefund = false;
        if (isset($order['paid_back_value_wt']) && 0 != $order['paid_back_value_wt'] && 0 != $order['refunded_price_wt']) {
            $hasRefund = true;
        }

        return $hasRefund;
    }

    protected function getFinalPaymentPageUrl(string $payment_status, \WC_Order $order): string
    {
        if (in_array($payment_status, self::PAYMENT_CANCELLED_STATUSES, true)) {
            $url = wc_get_checkout_url();

            return add_query_arg(PaymentGateway::FLASH_QUERY_ARG, self::FLASH_STATUS_CANCELLED, $url);
        }

        // return url return
        $return_url = $order->get_checkout_order_received_url();
        if (is_ssl() || 'yes' == get_option('woocommerce_force_ssl_checkout')) {
            $return_url = str_replace('http:', 'https:', $return_url);
        }

        $url = apply_filters('woocommerce_get_return_url', $return_url, $order);

        $status = $order->get_status('edit');
        if (StoreKeeperBaseGateway::ORDER_STATUS_PENDING === $status) {
            $url = add_query_arg(PaymentGateway::FLASH_QUERY_ARG, self::FLASH_STATUS_PENDING, $url);
        } elseif (StoreKeeperBaseGateway::ORDER_STATUS_ON_HOLD === $status) {
            $url = add_query_arg(PaymentGateway::FLASH_QUERY_ARG, self::FLASH_STATUS_ON_HOLD, $url);
        }

        return $url;
    }

    public static function getCheckOutLogger(): Logger
    {
        $checkOutLogger = LoggerFactory::create('checkout');

        return $checkOutLogger;
    }

    protected function isPaymentStatusPaid($payment_status): bool
    {
        return in_array($payment_status, self::PAYMENT_PAID_STATUSES, true);
    }
}
