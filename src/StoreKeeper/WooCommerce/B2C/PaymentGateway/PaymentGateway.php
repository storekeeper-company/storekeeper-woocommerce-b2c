<?php

namespace StoreKeeper\WooCommerce\B2C\PaymentGateway;

use Monolog\Logger;
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
     * @param $amount
     *
     * @return bool whenever the payment update was success or not
     */
    public static function updatePayment(int $order_id, int $payment_id, string $amount, bool $is_synced = false)
    {
        PaymentModel::update(
            $order_id,
            [
                'payment_id' => $payment_id,
                'amount' => $amount,
            ]
        );
    }

    /**
     * @param $order_id
     * @param $payment_id
     * @param $amount
     *
     * @return bool
     */
    public static function addPayment(int $order_id, int $payment_id, string $amount, bool $is_synced = false)
    {
        return PaymentModel::create(
            [
                'order_id' => $order_id,
                'payment_id' => $payment_id,
                'amount' => $amount,
                'is_synced' => $is_synced,
            ]
        );
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
            $payment_id = self::getPaymentId($order->get_id());

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
            $statusText = __('The order\'s payment status received: %s', I18N::DOMAIN);
            $order->add_order_note(sprintf($statusText, $payment_status));

            if (in_array($payment_status, self::PAYMENT_PAID_STATUSES, true)) {
                $checkOutLogger->debug('Payment paid', $log_context);
                $order->set_status(StoreKeeperBaseGateway::ORDER_STATUS_PROCESSING);
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

                $hasRefund = $this->storekeeperOrderHasRefundWithReturnPayment($order);

                if ($hasRefund) {
                    $isRefundCreationAllowed = false;
                    LoggerFactory::create('refund')->error('Order has refund on BackOffice already', ['order_id' => $orderId, 'storekeeper_id' => $storeKeeperOrderId]);
                }
            }
        }

        // This will prevent creation of refund
        if (!$isRefundCreationAllowed) {
            throw new \RuntimeException('Refund is not allowed to be created due to certain conditions');
        }
    }

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
     *
     * @return bool
     */
    public static function refundExists($orderId, $refundId): ?bool
    {
        global $wpdb;

        $payment_id = null;
        $table_name = RefundModel::getTableName();

        $sql = <<<SQL
SELECT sk_refund_id
FROM `$table_name`
WHERE wc_order_id = '$orderId'
AND wc_refund_id = '$refundId'
LIMIT 1
SQL;

        // Getting the results and getting the first one.
        $results = $wpdb->get_results($sql, ARRAY_A);
        if (!empty($results)) {
            $payment_id = array_shift($results)['sk_refund_id'];
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

    public static function getPaymentAmount($order_id)
    {
        global $wpdb;

        $amount = null;
        $table_name = PaymentModel::getTableName();

        $sql = <<<SQL
SELECT amount
FROM `$table_name`
WHERE order_id = '$order_id'
LIMIT 1
SQL;

        // Getting the results and getting the first one.
        $results = $wpdb->get_results($sql, ARRAY_A);
        if (!empty($results)) {
            $amount = array_shift($results)['amount'];
        }

        return $amount;
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
        if (StoreKeeperBaseGateway::ORDER_STATUS_PROCESSING !== $order->get_status()) {
            //old orders may not have order_id and payment_id linked or orders that didn't use the Payment Gateway
            $payment_id = self::getPaymentId($order_id);
            if ($payment_id) {
                $api = StoreKeeperApi::getApiByAuthName();
                $shop_module = $api->getModule('ShopModule');
                $payment = $shop_module->syncWebShopPaymentWithReturn($payment_id);

                if (in_array($payment['status'], ['paid', 'authorized'], true)) {
                    //payment in backend is marked as paid
                    $order->set_status(StoreKeeperBaseGateway::ORDER_STATUS_PROCESSING);
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
            $this->getCheckOutLogger()->error($authException->getMessage(), ['trace' => $authException->getTraceAsString()]);
            LoggerFactory::createErrorTask('add-storeKeeper-gateway-auth', $authException);

            return $default_gateway_classes;
        }

        return array_merge($default_gateway_classes, $gateway_classes);
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
}
