<?php

namespace StoreKeeper\WooCommerce\B2C\Exports;

use Exception;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Endpoints\WebService\AddressSearchEndpoint;
use StoreKeeper\WooCommerce\B2C\Exceptions\ExportException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\PaymentGateway\PaymentGateway;
use StoreKeeper\WooCommerce\B2C\Tools\CustomerFinder;
use StoreKeeper\WooCommerce\B2C\Tools\OrderHandler;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use WC_Order;
use WC_Product;
use WC_Product_Factory;

class OrderExport extends AbstractExport
{
    const CONTEXT = 'edit';
    const ROW_SHIPPING_METHOD_TYPE = 'shipping_method';
    const ROW_FEE_TYPE = 'fee';
    const ROW_PRODUCT_TYPE = 'product';

    protected function getFunction()
    {
        return 'WC_Order';
    }

    protected function getFunctionMultiple()
    {
        return 'get_posts';
    }

    protected function getMetaFunction()
    {
        return 'get_post_meta';
    }

    protected function getArguments()
    {
        return [
            'numberposts' => -1,
            'post_type' => wc_get_order_types(),
            'post_status' => array_keys(wc_get_order_statuses()),
        ];
    }

    /**
     * @param WC_Order $WpObject
     *
     * @return bool|mixed
     *
     * @throws Exception
     */
    protected function processItem($WpObject)
    {
        $this->debug('Exporting order with id '.$WpObject->get_id());
        if ('eur' !== strtolower(get_woocommerce_currency())) {
            $iso = get_woocommerce_currency();
            throw new Exception("Orders with woocommerce with currency_iso3 '$iso' are not supported");
        }

        if ($WpObject->get_id() <= 0) {
            throw new Exception("Order with id {$WpObject->get_id()} does not exists.");
        }

        $isUpdate = $this->already_exported();
        $isGuest = false === $WpObject->get_user();

        $callData = [
            'customer_comment' => !empty($WpObject->get_customer_note(self::CONTEXT)) ? $WpObject->get_customer_note(
                self::CONTEXT
            ) : null,
            'billing_address__merge' => false,  // defaults to true when not set
            'shipping_address__merge' => false, // defaults to true when not set
            'force_order_if_product_not_active' => true,
            'force_backorder_if_not_on_stock' => true,
        ];

        // Adding the shop order number on order creation.
        if (!$isUpdate) {
            $callData['shop_order_number'] = $WpObject->get_id();
        }

        $this->debug('started export of order', $callData);

        if ($isGuest) {
            $callData['customer_reference'] = get_bloginfo('name')
                .PHP_EOL
                .__('Customer Email', I18N::DOMAIN).': '.$WpObject->get_billing_email(self::CONTEXT)
                .PHP_EOL
                .__('Customer phone number', I18N::DOMAIN).': '.$WpObject->get_billing_phone(self::CONTEXT);
        } else {
            $callData['customer_reference'] = get_bloginfo('name');
        }
        $this->debug('Added site information', $callData);

        /*
         * Customer
         */
        if (!$isUpdate) {
            try {
                $callData['is_anonymous'] = false;
                $callData['relation_data_id'] = CustomerFinder::ensureCustomerFromOrder($WpObject);
            } catch (GeneralException $exception) {
                $callData['is_anonymous'] = true; // sets the order to anonymous;
            }
        }

        $this->debug('Added guest information', $callData);

        $ShopModule = $this->storekeeper_api->getModule('ShopModule');
        /*
         * Order products
         */
        if (!$isUpdate) {
            $callData['order_items'] = $this->getOrderItems($WpObject);
            $this->debug('Added order_items information', $callData);
        } else {
            $storekeeperId = $this->get_storekeeper_id();
            $storekeeperOrder = $ShopModule->getOrder($storekeeperId, null);

            $hasDifference = $this->checkOrderDifference($WpObject, $storekeeperOrder);
            // Only update order items if they have difference and order is not paid yet
            if ($hasDifference && !$storekeeperOrder['is_paid']) {
                $callData['order_items'] = $this->getOrderItems($WpObject);
                $this->debug('Updated order_items information due to difference with the backoffice order items', $callData);
            } elseif ($hasDifference && $storekeeperOrder['is_paid']) {
                $this->debug('Cannot synchronize order, there are differences but order is already paid',
                    array_merge(
                        ['shop_order_id' => $WpObject->get_id()],
                        $callData
                    ));
                throw new Exception('Order is paid but has differences, synchronization fails');
            } else {
                $callData['order_items__do_not_change'] = true;
                $callData['order_items__remove'] = null;
                $this->debug('Update, ignored order_items', $callData);
            }
        }

        /*
         * Add coupon codes
         */
        if ($coupons = $WpObject->get_coupons()) {
            $orderCouponCodes = [];
            foreach ($coupons as $couponId => $coupon) {
                $orderCouponCodes[] = [
                    'code' => $coupon->get_code(),
                    'value_wt' => wc_get_order_item_meta($couponId, 'discount_amount', true),
                ];
            }
            $callData['order_coupon_codes'] = $orderCouponCodes;
            $this->debug('Added coupon codes that were found in the order: ', $orderCouponCodes);
        } else {
            $this->debug('No coupon codes where added, because none where found in the order');
        }

        /*
         * Billing address
         */
        $customerId = $WpObject->get_customer_id();
        $billingHouseNumber = '';
        if (0 !== $customerId && AddressSearchEndpoint::DEFAULT_COUNTRY_ISO === $WpObject->get_billing_country(self::CONTEXT)) {
            $customer = new \WC_Customer($customerId);
            $houseNumber = $customer->get_meta('billing_address_house_number', true);
            if (!empty($houseNumber)) {
                $billingHouseNumber = $houseNumber.' ';
            }
        }

        $callData['billing_address'] = [
            'name' => $WpObject->get_formatted_billing_full_name(),
            'address_billing' => [
                'state' => $WpObject->get_billing_state(self::CONTEXT),
                'city' => $WpObject->get_billing_city(self::CONTEXT),
                'zipcode' => $WpObject->get_billing_postcode(self::CONTEXT),
                'street' => $billingHouseNumber.trim($WpObject->get_billing_address_1(self::CONTEXT)).' '.trim(
                        $WpObject->get_billing_address_2(self::CONTEXT)
                    ),
                'country_iso2' => $WpObject->get_billing_country(self::CONTEXT),
                'name' => $WpObject->get_formatted_billing_full_name(),
            ],
            'contact_set' => [
                'email' => $WpObject->get_billing_email(self::CONTEXT),
                'phone' => $WpObject->get_billing_phone(self::CONTEXT),
                'name' => $WpObject->get_formatted_billing_full_name(),
            ],
            'contact_person' => [
                'firstname' => $WpObject->get_billing_first_name(self::CONTEXT),
                'familyname' => $WpObject->get_billing_last_name(self::CONTEXT),
            ],
        ];

        if (!empty($WpObject->get_billing_company(self::CONTEXT))) {
            $callData['billing_address']['business_data'] = [
                'name' => $WpObject->get_billing_company(self::CONTEXT),
                'country_iso2' => $WpObject->get_billing_country(self::CONTEXT),
            ];
        }
        $this->debug('Added billing_address information', $callData);

        /*
         * Shipping address
         */
        $customerId = $WpObject->get_customer_id();
        $shippingHouseNumber = '';
        if (0 !== $customerId && AddressSearchEndpoint::DEFAULT_COUNTRY_ISO === $WpObject->get_billing_country(self::CONTEXT)) {
            $customer = new \WC_Customer($customerId);
            $houseNumber = $customer->get_meta('shipping_address_house_number', true);
            if (!empty($houseNumber)) {
                $shippingHouseNumber = $houseNumber.' ';
            }
        }
        if ($WpObject->has_shipping_address()) {
            $callData['shipping_address'] = [
                'name' => $WpObject->get_formatted_shipping_full_name(),
                'contact_address' => [
                    'state' => $WpObject->get_shipping_state(self::CONTEXT),
                    'city' => $WpObject->get_shipping_city(self::CONTEXT),
                    'zipcode' => $WpObject->get_shipping_postcode(self::CONTEXT),
                    'street' => $shippingHouseNumber.trim($WpObject->get_shipping_address_1(self::CONTEXT)).' '.trim(
                            $WpObject->get_shipping_address_2(self::CONTEXT)
                        ),
                    'country_iso2' => $WpObject->get_shipping_country(self::CONTEXT),
                    'name' => $WpObject->get_formatted_shipping_full_name(),
                ],
                'contact_set' => [
                    'email' => $WpObject->get_billing_email(self::CONTEXT),
                    'phone' => $WpObject->get_billing_phone(self::CONTEXT),
                    'name' => $WpObject->get_formatted_shipping_full_name(),
                ],
                'contact_person' => [
                    'firstname' => $WpObject->get_shipping_first_name(self::CONTEXT),
                    'familyname' => $WpObject->get_shipping_last_name(self::CONTEXT),
                ],
            ];

            if (!empty($WpObject->get_shipping_company(self::CONTEXT))) {
                $callData['shipping_address']['business_data'] = [
                    'name' => $WpObject->get_shipping_company(self::CONTEXT),
                    'country_iso2' => $WpObject->get_shipping_country(self::CONTEXT),
                ];
            }
            $this->debug('Added shipping_address information', $callData);
        } else {
            $callData['shipping_address'] = $callData['billing_address'];
            $this->debug('Added billing_address as shipping_address information', $callData);
        }

        /*
         * Create or update the order.
         */
        if ($isUpdate) {
            $storekeeper_id = $this->get_storekeeper_id();
            $ShopModule->updateOrder($callData, $this->get_storekeeper_id());
        } else {
            $storekeeper_id = $ShopModule->newOrder($callData);
            WordpressExceptionThrower::throwExceptionOnWpError(
                update_post_meta($WpObject->get_id(), 'storekeeper_id', $storekeeper_id)
            );
        }

        // Add last sync date meta for orders
        // Time will be based on user's selected timezone on wordpress
        $date = current_time('mysql');
        WordpressExceptionThrower::throwExceptionOnWpError(
            update_post_meta($WpObject->get_id(), 'storekeeper_sync_date', $date)
        );
        $this->debug('Saved order data', $storekeeper_id);

        // StoreKeeper order variable.
        $storekeeper_order = null;

        /*
         * Attach payment to order
         */
        if (
            // Check if there is an payment
            PaymentGateway::hasPayment($WpObject->get_id()) &&
            // Check if the payment was not synced yet.
            !PaymentGateway::isPaymentSynced($WpObject->get_id())
        ) {
            $gateway_payment_id = PaymentGateway::getPaymentId($WpObject->get_id());
            $this->debug(
                'Attaching payment to order',
                ['order_id' => $storekeeper_id, 'payment_id' => $gateway_payment_id]
            );

            // Check for the error being thrown.
            try {
                $ShopModule->attachPaymentIdsToOrder(['payment_ids' => [$gateway_payment_id]], $storekeeper_id);
            } catch (GeneralException $generalException) {
                // Check if the payment is already attached to the order, we can ignore it an move on.
                $strpos = strpos($generalException->getMessage(), 'This payment is already linked');
                if (!is_numeric($strpos)) {
                    throw $generalException;
                }
            }
            PaymentGateway::markPaymentAsSynced($WpObject->get_id());
        } else { // Check if the WP order is paid and has not PaymentGateway payment.
            // Example: the order was paid using the PayNL plugin
            if (
                $WpObject->is_paid() &&
                !PaymentGateway::hasPayment($WpObject->get_id())
            ) {
                /**
                 * Retrieve the current payment status of the order from the backend.
                 * When the invoice is already payed, we should't set the payment again.
                 */
                $storekeeper_order = $ShopModule->getOrder($storekeeper_id, null);
                $storekeeper_is_paid = (bool) $storekeeper_order['is_paid'];
                $this->debug('Backend payment state of this order', json_encode($storekeeper_is_paid));

                // Order paid in WP but not in the Backoffice.
                if (!$storekeeper_is_paid) {
                    // Try to mark the order as paid in the backoffice
                    try {
                        $PaymentModule = $this->storekeeper_api->getModule('PaymentModule');
                        $response = $PaymentModule->listProviderMethodTypes(
                            0,
                            1,
                            null,
                            [
                                [
                                    'name' => 'alias__=',
                                    'val' => 'Web',
                                ],
                                [
                                    'name' => 'module_name__=',
                                    'val' => 'PaymentModule',
                                ],
                            ]
                        );

                        $provider_method_type_id = null;
                        if (empty($response['data'])) {
                            $this->debug('No Web ProviderMethodTypes found', $storekeeper_id);
                        } else {
                            $provider_method_type_id = $response['data'][0]['id'];
                        }

                        $paymentGateway = wc_get_payment_gateway_by_order($WpObject);
                        if ($paymentGateway) {
                            $paymentGatewayTitle = $paymentGateway->get_method_title();
                            $comment = $paymentGatewayTitle.' ('.__('Wordpress plugin').')';
                        } else {
                            $comment = ucwords(str_replace('pay_gateway_', '', $WpObject->get_payment_method()));
                        }

                        if (!empty($WpObject->get_meta('transactionId'))) {
                            $comment .= ' #'.$WpObject->get_meta('transactionId');
                        } elseif (!empty($WpObject->get_transaction_id())) {
                            $comment .= ' #'.$WpObject->get_transaction_id();
                        }

                        $ShopModule->markOrderAsPaid(
                            $storekeeper_id,
                            [
                                'comment' => $comment,
                                'provider_method_type_id' => $provider_method_type_id,
                            ]
                        );
                    } catch (GeneralException $generalException) {
                        // If the error message is not that the order was already paid, Re-throw the order.
                        if ('Order is already paid' !== trim($generalException->getMessage())) {
                            throw $generalException;
                        }
                    }

                    $this->debug('The order is paid: Marked the order as paid. '.$comment);
                } else {
                    $this->debug('Did not mark the order as paid since it is not paid yet according to WooCommerce');
                }
            } else {
                $this->debug('Did not mark the order as paid since it was not needed');
            }
        }

        /**
         * Status.
         */

        // Refetch the order to make sure we have the latest
        $storekeeper_order = $ShopModule->getOrder($storekeeper_id, null);

        // Get the storekeeper and converted woocommerce status.
        $storekeeper_status = $storekeeper_order['status'];
        $woocommerce_status = self::convertWooCommerceToStorekeeperOrderStatus($WpObject->get_status(self::CONTEXT));

        // Check if we should update the status
        if ($this->shouldUpdateStatus($storekeeper_status, $woocommerce_status)) {
            $ShopModule->updateOrderStatus(['status' => $woocommerce_status], $storekeeper_id);
            $this->debug(
                'Updated backend/backoffice status',
                [
                    'wordpress' => $woocommerce_status,
                    'storekeeper' => $storekeeper_status,
                ]
            );
        } else {
            // No update is needed
            $this->debug(
                'Skipped updating backend/backoffice status',
                [
                    'wordpress' => $woocommerce_status,
                    'storekeeper' => $storekeeper_status,
                ]
            );
        }

        return true;
    }

    const STATUS_NEW = 'new';
    const STATUS_PROCESSING = 'processing';
    const STATUS_ON_HOLD = 'on_hold';
    const STATUS_COMPLETE = 'complete';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * @param $wc_order_status
     *
     * @return string
     */
    public static function convertWooCommerceToStorekeeperOrderStatus($wc_order_status)
    {
        switch ($wc_order_status) {
            case 'completed':
                return self::STATUS_COMPLETE;
            case 'processing':
                return self::STATUS_PROCESSING;
            case 'refunded':
                return self::STATUS_REFUNDED;
            case 'pending':
                return self::STATUS_NEW;
            case 'failed':
            case 'cancelled':
                return self::STATUS_CANCELLED;
            case 'on-hold':
            default:
                return self::STATUS_ON_HOLD;
        }
    }

    /**
     * @param WC_Order $databaseOrder - Order items to compare from
     * @param $backofficeOrder - Order items to compare to
     */
    public function checkOrderDifference(WC_Order $databaseOrder, $backofficeOrder): bool
    {
        $databaseOrderItems = $this->getOrderItems($databaseOrder);
        $backofficeOrderItems = $backofficeOrder['order_items'];

        $allHasExtras = true;
        foreach ($backofficeOrderItems as $backofficeOrderItem) {
            if (!array_key_exists('extra', $backofficeOrderItem)) {
                $allHasExtras = false;
                break;
            }
        }

        if ($allHasExtras) {
            $hasDifference = $this->checkOrderDifferenceByExtra($databaseOrderItems, $backofficeOrderItems);
        } else {
            $hasDifference = $this->checkOrderDifferenceBySet($databaseOrderItems, $backofficeOrderItems);
        }

        // In case order items are the same but prices have changed. e.g payment gateway fee
        if (
            !$hasDifference &&
            round(((float) $databaseOrder->get_total()), 2) !== round($backofficeOrder['value_wt'], 2)
        ) {
            $hasDifference = true;
        }

        return $hasDifference;
    }

    private function checkOrderDifferenceByExtra(array $databaseOrderItems, array $backofficeOrderItems): bool
    {
        $databaseOrderItemExtras = array_column($databaseOrderItems, 'extra');
        $backofficeOrderItemExtras = array_column($backofficeOrderItems, 'extra');

        foreach ($databaseOrderItemExtras as &$extras) {
            array_multisort($extras);
            unset($extras);
        }

        foreach ($backofficeOrderItemExtras as &$extras) {
            array_multisort($extras);
            unset($extras);
        }

        sort($databaseOrderItemExtras);
        sort($backofficeOrderItemExtras);

        return $databaseOrderItemExtras !== $backofficeOrderItemExtras;
    }

    private function checkOrderDifferenceBySet(array $databaseOrderItems, array $backofficeOrderItems): bool
    {
        $databaseSet = [];
        foreach ($databaseOrderItems as $databaseOrderItem) {
            $databaseSet[] = (
                (int) $databaseOrderItem['quantity']).'|'
                .round($databaseOrderItem['ppu_wt'], 2).'|'
                .$databaseOrderItem['sku'];
        }

        $backofficeSet = [];
        foreach ($backofficeOrderItems as $backofficeOrderItem) {
            $backofficeSet[] = (
                (int) $backofficeOrderItem['quantity']).'|'
                .round($backofficeOrderItem['ppu_wt'], 2).'|'
                .$backofficeOrderItem['sku'];
        }

        sort($databaseSet);
        sort($backofficeSet);

        return $databaseSet !== $backofficeSet;
    }

    /**
     * @param $storekeeper_status string The current StoreKeeper order status
     * @param $woocommerce_status string The woocommerce status, converted to its storekeeper status
     *
     * @return bool
     */
    public function shouldUpdateStatus($storekeeper_status, $woocommerce_status)
    {
        // Check if the status has not changed
        if ($storekeeper_status === $woocommerce_status) {
            return false;
        } else {
            // The following checks are taken from the Backend (modules/ShopModule/libs/Order/OrderManager.php:678, the update function)
            if (
                self::STATUS_REFUNDED === $storekeeper_status ||
                self::STATUS_CANCELLED === $storekeeper_status
            ) {
                return false;
            }

            if (
                self::STATUS_COMPLETE === $storekeeper_status &&
                self::STATUS_REFUNDED !== $woocommerce_status
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $order WC_Order
     *
     * @return array
     *
     * @throws Exception
     */
    private function getOrderItems($order)
    {
        $order_items = [];
        $this->debug('Adding product items');
        $_pf = new WC_Product_Factory();

        $order_metadata = $order->get_meta_data();

        $shop_product_id_map = [];
        foreach ($order_metadata as $metadata) {
            $data = $metadata->get_data();
            if (OrderHandler::SHOP_PRODUCT_ID_MAP === $data['key']) {
                $shop_product_id_map = $data['value'];
            }
        }

        /**
         * @var $orderProduct \WC_Order_Item_Product
         */
        foreach ($order->get_items() as $index => $orderProduct) {
            $this->debug($index.' Adding product item');

            $var_prod_id = $orderProduct->get_variation_id();
            $is_variation = $var_prod_id > 0; // Variation_id is 0 my default, if it is any other, its a variation products;

            $attribute_ids = [];
            if ($is_variation) {
                $product_id = $var_prod_id;
            } else {
                $product_id = $orderProduct->get_product_id();
            }
            $currentProduct = $_pf->get_product($product_id);

            if (array_key_exists($product_id, $shop_product_id_map)) {
                $shop_product_id = $shop_product_id_map[$product_id];
            } else {
                $shop_product_id = $this->fetchShopProductId($currentProduct);
            }

            $description = '';
            if ($is_variation) {
                /**
                 * @var $meta_datum \WC_Meta_Data
                 *                  This is the meta data that is set on the product order, for a variation product that.
                 *                  More info about meta_data: https://docs.woocommerce.com/wc-apidocs/class-WC_Data.html
                 */
                $meta_data = $orderProduct->get_meta_data();
                $meta = [];
                foreach ($meta_data as $meta_datum) {
                    $data = $meta_datum->get_data();
                    $meta[] = $data['value'];
                }
                $description = implode(', ', $meta);
            }

            $this->debug('Got product', $currentProduct);

            $data = [
                'sku' => $currentProduct ? $currentProduct->get_sku(self::CONTEXT) : $orderProduct->get_name(
                    self::CONTEXT
                ),
                'ppu_wt' => $order->get_item_total($orderProduct, true, false), //get price with discount
                'before_discount_ppu_wt' => $order->get_item_subtotal($orderProduct, true, false), //get without discount
                'quantity' => $orderProduct->get_quantity(self::CONTEXT),
                'name' => $currentProduct ? $currentProduct->get_name(self::CONTEXT) : $orderProduct->get_name(
                    self::CONTEXT
                ),
                'description' => $description,
                'shop_product_id' => $shop_product_id,
            ];

            if (count($attribute_ids) > 0) {
                $data['attribute_option_ids'] = $attribute_ids;
            }

            $extra = [
                'wp_row_id' => $orderProduct->get_id(),
                'wp_row_md5' => md5(json_encode($orderProduct->get_data(), JSON_THROW_ON_ERROR)),
                'wp_row_type' => self::ROW_PRODUCT_TYPE,
            ];

            $data['extra'] = $extra;

            $order_items[] = $data;

            $this->debug($index.' Added product item');
        }

        $this->debug('Added product items');

        /**
         * @var $fee \WC_Order_Item_Fee
         */
        foreach ($order->get_fees() as $fee) {
            $data = [
                'sku' => strtolower($fee->get_name(self::CONTEXT)),
                'ppu_wt' => $order->get_item_total($fee, true, false),
                'quantity' => $fee->get_quantity(),
                'name' => $fee->get_name(self::CONTEXT),
            ];

            $extra = [
                'wp_row_id' => $fee->get_id(),
                'wp_row_md5' => md5(json_encode($fee->get_data(), JSON_THROW_ON_ERROR)),
                'wp_row_type' => self::ROW_FEE_TYPE,
            ];

            $data['extra'] = $extra;
            $order_items[] = $data;
        }

        $this->debug('Added fee items');

        /**
         * @var $shipping_method \WC_Order_Item_Shipping
         */
        foreach ($order->get_shipping_methods() as $shipping_method) {
            $data = [
                'sku' => strtolower($shipping_method->get_name(self::CONTEXT)),
                'ppu_wt' => $order->get_item_total($shipping_method, true, false),
                'quantity' => $shipping_method->get_quantity(),
                'name' => $shipping_method->get_name(self::CONTEXT),
                'is_shipping' => true,
            ];

            $extra = [
                'wp_row_id' => $shipping_method->get_id(),
                'wp_row_md5' => md5(json_encode($shipping_method->get_data(), JSON_THROW_ON_ERROR)),
                'wp_row_type' => self::ROW_SHIPPING_METHOD_TYPE,
            ];

            $data['extra'] = $extra;

            $order_items[] = $data;
        }
        $this->debug('Added shipping items');

        return $order_items;
    }

    private function fetchShopProductId(WC_Product $product): ?int
    {
        $postId = $product->get_id();
        $storekeeperId = get_post_meta($postId, 'storekeeper_id', true) ?? null;

        if (!$storekeeperId) {
            $storekeeperId = $this->getStorekeeperIdBySku($product->get_sku());

            if ($storekeeperId) {
                add_post_meta(
                    $postId,
                    'storekeeper_id',
                    $storekeeperId,
                    true
                );
            }
        }

        return $storekeeperId;
    }

    private function getStorekeeperIdBySku($sku): ?int
    {
        $ShopModule = $this->storekeeper_api->getModule('ShopModule');
        $response = $ShopModule->naturalSearchShopFlatProductForHooks(
            0,
            0,
            0,
            1,
            null,
            [
                [
                    'name' => 'flat_product/product/sku__=',
                    'val' => $sku,
                ],
            ]
        );

        if ($product = current($response['data'])) {
            return (int) $product['id'];
        }

        return null;
    }

    protected function catchKnownExceptions($throwable)
    {
        if (($throwable instanceof GeneralException) && 'ShopModule::OrderDuplicateNumber' === $throwable->getApiExceptionClass()) {
            return new ExportException(
                esc_html__('Order with this order number already exists.', I18N::DOMAIN),
                $throwable->getCode(),
                $throwable
            );
        }

        return parent::catchKnownExceptions($throwable);
    }
}
