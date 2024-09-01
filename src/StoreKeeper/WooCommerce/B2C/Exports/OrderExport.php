<?php

namespace StoreKeeper\WooCommerce\B2C\Exports;

use Automattic\WooCommerce\Utilities\NumberUtil;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Backoffice\BackofficeCore;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Endpoints\WebService\AddressSearchEndpoint;
use StoreKeeper\WooCommerce\B2C\Exceptions\ExportException;
use StoreKeeper\WooCommerce\B2C\Exceptions\OrderDifferenceException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\PaymentModel;
use StoreKeeper\WooCommerce\B2C\PaymentGateway\PaymentGateway;
use StoreKeeper\WooCommerce\B2C\Tools\CustomerFinder;
use StoreKeeper\WooCommerce\B2C\Tools\OrderHandler;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use WC_Meta_Data;
use WC_Order;
use WC_Order_Item_Product;

class OrderExport extends AbstractExport
{
    public const EMBALLAGE_TAX_RATE_ID_META_KEY = 'sk_emballage_tax_id';
    public const IS_EMBALLAGE_FEE_KEY = 'sk_emballage_fee';
    public const TAX_RATE_ID_FEE_KEY = 'sk_tax_rate_id';
    public const CONTEXT = 'edit';
    public const ROW_SHIPPING_METHOD_TYPE = 'shipping_method';
    public const ROW_FEE_TYPE = 'fee';
    public const ROW_PRODUCT_TYPE = 'product';
    public const ROW_PRODUCT_DIFF_TYPE = 'product_diff';

    public const STATUS_NEW = 'new';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    public const EXTRA_ROW_ID_KEY = 'wp_row_id';
    public const EXTRA_ROW_MD5_KEY = 'wp_row_md5';
    public const EXTRA_ROW_TYPE = 'wp_row_type';
    public const EXTRA_ROW_META = 'wp_row_meta';
    public const EXTRA_ADDON_GROUP_ID = 'product_addon_group_id';

    public const SUBITEM_FIELD_PREFIX = 'sk_subitem';
    public const CART_FIELD_ID = self::SUBITEM_FIELD_PREFIX.'_id';
    public const CART_FIELD_NAME = self::SUBITEM_FIELD_PREFIX.'_name';
    public const CART_FIELD_SHOP_PRODUCT_ID = self::SUBITEM_FIELD_PREFIX.'_shop_product_id';
    public const CART_FIELD_ADDON_GROUP_ID = self::SUBITEM_FIELD_PREFIX.'_product_addon_group_id';
    public const CART_FIELD_PARENT_ID = self::SUBITEM_FIELD_PREFIX.'_parent_id';

    private const EXTRA_KEYS_FOR_MD5_COMPARE = [
        self::EXTRA_ROW_ID_KEY,
        self::EXTRA_ROW_TYPE,
        self::EXTRA_ADDON_GROUP_ID,
    ];

    public const MAXIMUM_DUPLICATE_COUNT = 3;

    private ?int $shopOrderId = null;
    private \WC_Order $order;

    protected function getFunction()
    {
        return 'WC_Order';
    }

    protected function getFunctionMultiple()
    {
        return 'get_posts';
    }

    protected function getStoreKeeperIdFromMeta()
    {
        if (BackofficeCore::isHighPerformanceOrderStorageReady()) {
            return $this->order->get_meta('storekeeper_id');
        }

        return parent::getStoreKeeperIdFromMeta();
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
     * @param \WC_Order $order
     *
     * @throws \Exception
     */
    protected function processItem($order): void
    {
        global $storekeeper_ignore_order_id;
        $this->order = $order;
        $this->shopOrderId = $shopOrderId = $order->get_id();
        $this->debug('Exporting order with id '.$shopOrderId);
        if ('eur' !== strtolower(get_woocommerce_currency())) {
            $iso = get_woocommerce_currency();
            throw new \Exception("Orders with woocommerce with currency_iso3 '$iso' are not supported");
        }

        if ($shopOrderId <= 0) {
            throw new \Exception("Order with id {$shopOrderId} does not exists.");
        }

        if ('checkout-draft' === $order->get_status()) {
            throw new \Exception('Orders with checkout-draft status should not be exported');
        }

        $isUpdate = $this->already_exported();
        $isGuest = false === $order->get_user();

        $callData = [
            'customer_comment' => !empty($order->get_customer_note(self::CONTEXT)) ? $order->get_customer_note(
                self::CONTEXT
            ) : null,
            'billing_address__merge' => false,  // defaults to true when not set
            'shipping_address__merge' => false, // defaults to true when not set
            'force_order_if_product_not_active' => true,
            'force_backorder_if_not_on_stock' => true,
        ];

        // Adding the shop order number on order creation.
        if (!$isUpdate) {
            $callData['shop_order_number'] = $shopOrderId;
        }

        $this->debug('started export of order', $callData);

        if ($isGuest) {
            $callData['customer_reference'] = get_bloginfo('name')
                .PHP_EOL
                .__('Customer Email', I18N::DOMAIN).': '.$order->get_billing_email(self::CONTEXT)
                .PHP_EOL
                .__('Customer phone number', I18N::DOMAIN).': '.$order->get_billing_phone(self::CONTEXT);
        } else {
            $callData['customer_reference'] = get_bloginfo('name');
        }
        $this->debug('Added site information', $callData);

        /*
         * Customer
         */
        if (!$isUpdate) {
            // General rule to never send anonymous order from web shop
            $callData['is_anonymous'] = false;
            $callData['relation_data_id'] = CustomerFinder::ensureCustomerFromOrder($order);
        }

        $this->debug('Added guest information', $callData);

        $ShopModule = $this->storekeeper_api->getModule('ShopModule');
        /*
         * Order products
         */
        if (!$isUpdate) {
            $callData['order_items'] = $this->getOrderItems($order);
            $this->debug('Added order_items information', $callData);
        } else {
            $storekeeperId = $this->get_storekeeper_id();
            $storekeeperOrder = $ShopModule->getOrder($storekeeperId, null);

            $hasDifference = false;
            /* @var OrderDifferenceException|null $differenceException */
            $differenceException = null;
            try {
                $this->checkOrderDifference($order, $storekeeperOrder);
            } catch (OrderDifferenceException $exception) {
                $hasDifference = true;
                $differenceException = $exception;
            }
            // Previously, order items were never updated, but issues arise when payment gateways
            // were changed, so they were never synched as they are part of order items.
            // Logic below is to only update order items if they have difference and order is not paid yet
            if ($hasDifference && !$storekeeperOrder['is_paid']) {
                $callData['order_items'] = $this->getOrderItems($order);
                $this->debug('Updated order_items information due to difference with the backoffice order items', $callData);
            } elseif ($hasDifference && $storekeeperOrder['is_paid']) {
                $this->debug('Cannot synchronize order, there are differences but order is already paid',
                    array_merge(
                        ['shop_order_id' => $shopOrderId],
                        $callData
                    ));
                throw new OrderDifferenceException("Order is paid but has differences ({$differenceException->getMessage()})", $differenceException->getShopExtras(), $differenceException->getBackofficeExtras(), $differenceException->getCode(), $differenceException);
            } else {
                $callData['order_items__do_not_change'] = true;
                $callData['order_items__remove'] = null;
                $this->debug('Update, ignored order_items', $callData);
            }
        }

        /*
         * Add coupon codes
         */
        if ($coupons = $order->get_coupons()) {
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
        $callData['billing_address'] = [
            'name' => $order->get_formatted_billing_full_name(),
            'address_billing' => [
                'state' => $order->get_billing_state(self::CONTEXT),
                'city' => $order->get_billing_city(self::CONTEXT),
                'zipcode' => $order->get_billing_postcode(self::CONTEXT),
                'street' => trim($order->get_billing_address_1(self::CONTEXT)).' '.trim(
                    $order->get_billing_address_2(self::CONTEXT)
                ),
                'country_iso2' => $order->get_billing_country(self::CONTEXT),
                'name' => !empty($order->get_billing_company(self::CONTEXT)) ? $order->get_billing_company(self::CONTEXT) : $order->get_formatted_billing_full_name(),
            ],
            'contact_set' => [
                'email' => $order->get_billing_email(self::CONTEXT),
                'phone' => $order->get_billing_phone(self::CONTEXT),
                'name' => $order->get_formatted_billing_full_name(),
            ],
            'contact_person' => [
                'firstname' => $order->get_billing_first_name(self::CONTEXT),
                'familyname' => $order->get_billing_last_name(self::CONTEXT),
            ],
        ];

        if (!empty($order->get_billing_company(self::CONTEXT))) {
            $callData['billing_address']['business_data'] = [
                'name' => $order->get_billing_company(self::CONTEXT),
                'country_iso2' => $order->get_billing_country(self::CONTEXT),
            ];
        }

        if (AddressSearchEndpoint::DEFAULT_COUNTRY_ISO === $order->get_billing_country(self::CONTEXT)) {
            $houseNumber = $order->get_meta('billing_address_house_number', true);
            if (!empty($houseNumber)) {
                $splitStreet = self::splitStreetNumber($houseNumber);
                $streetNumber = $splitStreet['streetnumber'];
                $callData['billing_address']['address_billing']['streetnumber'] = $streetNumber;

                if (!empty($splitStreet['flatnumber'])) {
                    $callData['billing_address']['address_billing']['flatnumber'] = $splitStreet['flatnumber'];
                }
            }
        }

        $this->debug('Added billing_address information', $callData);

        /*
         * Shipping address
         */
        if ($order->has_shipping_address()) {
            $callData['shipping_address'] = [
                'name' => $order->get_formatted_shipping_full_name(),
                'contact_address' => [
                    'state' => $order->get_shipping_state(self::CONTEXT),
                    'city' => $order->get_shipping_city(self::CONTEXT),
                    'zipcode' => $order->get_shipping_postcode(self::CONTEXT),
                    'street' => trim($order->get_shipping_address_1(self::CONTEXT)).' '.trim(
                        $order->get_shipping_address_2(self::CONTEXT)
                    ),
                    'country_iso2' => $order->get_shipping_country(self::CONTEXT),
                    'name' => $order->get_formatted_shipping_full_name(),
                ],
                'contact_set' => [
                    'email' => $order->get_billing_email(self::CONTEXT),
                    'phone' => $order->get_billing_phone(self::CONTEXT),
                    'name' => $order->get_formatted_shipping_full_name(),
                ],
                'contact_person' => [
                    'firstname' => $order->get_shipping_first_name(self::CONTEXT),
                    'familyname' => $order->get_shipping_last_name(self::CONTEXT),
                ],
            ];

            if (!empty($order->get_shipping_company(self::CONTEXT))) {
                $callData['shipping_address']['business_data'] = [
                    'name' => $order->get_shipping_company(self::CONTEXT),
                    'country_iso2' => $order->get_shipping_country(self::CONTEXT),
                ];
            }

            if (AddressSearchEndpoint::DEFAULT_COUNTRY_ISO === $order->get_shipping_country(self::CONTEXT)) {
                $houseNumber = $order->get_meta('shipping_address_house_number', true);
                if (!empty($houseNumber)) {
                    $splitStreet = self::splitStreetNumber($houseNumber);
                    $streetNumber = $splitStreet['streetnumber'];
                    $callData['shipping_address']['contact_address']['streetnumber'] = $streetNumber;

                    if (!empty($splitStreet['flatnumber'])) {
                        $callData['shipping_address']['contact_address']['flatnumber'] = $splitStreet['flatnumber'];
                    }
                }
            }
            $this->debug('Added shipping_address information', $callData);
        } else {
            $callData['shipping_address'] = $callData['billing_address'];
            $this->debug('Added billing_address as shipping_address information', $callData);
        }

        // Ignore order to avoid triggering order export again
        $storekeeper_ignore_order_id = $order->get_id();
        /*
         * Create or update the order.
         */
        if ($isUpdate) {
            $storekeeper_id = $this->get_storekeeper_id();
            $ShopModule->updateOrder($callData, $this->get_storekeeper_id());
        } else {
            $succeed = false;
            $duplicateCounter = 1;
            while (!$succeed) {
                try {
                    $storekeeper_id = $ShopModule->newOrder($callData);
                    $succeed = true;
                } catch (GeneralException $exception) {
                    if ($duplicateCounter <= self::MAXIMUM_DUPLICATE_COUNT && 'ShopModule::OrderDuplicateNumber' === $exception->getApiExceptionClass()) {
                        $callData['shop_order_number'] = "{$shopOrderId}($duplicateCounter)";
                        $this->debug("Attempting to create the order $duplicateCounter times", [
                            'shopOrderId' => $shopOrderId,
                            'shop_order_number' => $callData['shop_order_number'],
                        ]);
                        ++$duplicateCounter;
                    } else {
                        throw $exception;
                    }
                }
            }

            WordpressExceptionThrower::throwExceptionOnWpError(
                $order->add_meta_data('storekeeper_id', $storekeeper_id),
            );
            $order->save();
        }

        $date = DatabaseConnection::formatToDatabaseDate();
        WordpressExceptionThrower::throwExceptionOnWpError(
            $order->update_meta_data('storekeeper_sync_date', $date),
        );
        $this->debug('Saved order data', $storekeeper_id);

        // Handle payments and refunds
        $this->processPaymentsAndRefunds($order, $storekeeper_id);

        /**
         * Status.
         */

        // Refetch the order to make sure we have the latest
        $storekeeper_order = $ShopModule->getOrder($storekeeper_id, null);

        // Get the storekeeper and converted woocommerce status.
        $storekeeper_status = $storekeeper_order['status'];
        $woocommerce_status = self::convertWooCommerceToStorekeeperOrderStatus($order->get_status(self::CONTEXT));

        // Check if we should update the status
        if ($this->shouldUpdateStatus($storekeeper_status, $woocommerce_status)) {
            if (self::STATUS_REFUNDED === $woocommerce_status) {
                // This will change the status of order to refunded without sending payment ids
                // Will only be a fallback if refunding processPaymentsAndRefunds does not change the status to refunded
                $ShopModule->refundAllOrderItems([
                    'id' => $storekeeper_id,
                ]);
            } else {
                $ShopModule->updateOrderStatus(['status' => $woocommerce_status], $storekeeper_id);
            }
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

        if ($order->meta_exists(OrderHandler::TO_BE_SYNCHRONIZED_META_KEY)) {
            $order->delete_meta_data(OrderHandler::TO_BE_SYNCHRONIZED_META_KEY);
        }

        $order->save();
        // Remove ignore
        $storekeeper_ignore_order_id = null;
    }

    /**
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
     * @param \WC_Order $databaseOrder   - Order items to compare from
     * @param           $backofficeOrder - Order items to compare to
     *
     * @throws OrderDifferenceException
     */
    public function checkOrderDifference(\WC_Order $databaseOrder, $backofficeOrder): void
    {
        $databaseOrderItems = $this->getOrderItems($databaseOrder);
        $backofficeOrderItems = $backofficeOrder['order_items'];

        if (count($databaseOrderItems) !== count($backofficeOrderItems)) {
            throw new OrderDifferenceException('Order item count did not match');
        }

        $allHasExtras = true;
        foreach ($backofficeOrderItems as $backofficeOrderItem) {
            if (!array_key_exists('extra', $backofficeOrderItem)) {
                $allHasExtras = false;
                break;
            }
        }

        $this->removeSkippedItemsByName($databaseOrderItems, $backofficeOrderItems);

        if ($allHasExtras) {
            $this->checkOrderDifferenceByExtra($databaseOrderItems, $backofficeOrderItems, $databaseOrder);
        } else {
            $this->checkOrderDifferenceBySet($databaseOrderItems, $backofficeOrderItems);
        }

        // In case order items are the same but prices have changed. e.g payment gateway fee
        if (round((float) $databaseOrder->get_total(), 2) !== round($backofficeOrder['value_wt'], 2)) {
            $databaseTotal = (float) $databaseOrder->get_total();
            $backofficeTotal = (float) round($backofficeOrder['value_wt'], 2);
            $this->debug('Order has difference in prices', [
                'databaseTotal' => $databaseTotal,
                'backofficeTotal' => $backofficeTotal,
            ]);

            // The order is refunded so the value_wt is expected to have difference with database total
            if (isset($backofficeOrder['refund_price_wt']) && 0 === $backofficeOrder['refund_price_wt'] && self::STATUS_REFUNDED !== $backofficeOrder['status']) {
                throw new OrderDifferenceException("Prices are different, WooCommerce total is {$databaseTotal} while StoreKeeper total is {$backofficeTotal}");
            }
        }
    }

    private function removeSkippedItemsByName(&$databaseOrderItems, &$backofficeOrderItems): void
    {
        $skippedOrderItemNames = [];
        foreach ($databaseOrderItems as $index => $databaseOrderItem) {
            if (isset($databaseOrderItem['isSkipped']) && $databaseOrderItem['isSkipped']) {
                $skippedOrderItemNames[] = $databaseOrderItem['name'];
                unset($databaseOrderItems[$index]);
            }
        }

        foreach ($backofficeOrderItems as $index => $backofficeOrderItem) {
            // Ignore deleted woocommerce products from being
            // checked for difference based on its name (as the product no longer has ID)
            if (in_array($backofficeOrderItem['name'], $skippedOrderItemNames, true)) {
                unset($backofficeOrderItems[$index]);
            }
        }
    }

    /**
     * @throws OrderDifferenceException
     */
    public function checkOrderDifferenceByExtra(array $databaseOrderItems, array $backofficeOrderItems, \WC_Order $wcOrder): void
    {
        $databaseOrderItemExtras = array_column($databaseOrderItems, 'extra');
        $backofficeOrderItemExtras = array_column($backofficeOrderItems, 'extra');
        $this->cleanExtras($databaseOrderItemExtras);
        $this->cleanExtras($backofficeOrderItemExtras);

        // Start comparing product order items
        $databaseProductExtras = $this->filterExtrasByRowExtraType($databaseOrderItemExtras, self::ROW_PRODUCT_TYPE);
        $backofficeProductExtras = $this->filterExtrasByRowExtraType($backofficeOrderItemExtras, self::ROW_PRODUCT_TYPE);
        $isSame = $this->compareExtras($databaseProductExtras, $backofficeProductExtras);
        if (!$isSame) {
            throw new OrderDifferenceException('Product order items did not match', $databaseOrderItemExtras, $backofficeOrderItemExtras);
        }
        // End comparing product order items

        // Start comparing fee order items
        $databaseFeeExtras = $this->filterExtrasByRowExtraType($databaseOrderItemExtras, self::ROW_FEE_TYPE);
        $backofficeFeeExtras = $this->filterExtrasByRowExtraType($backofficeOrderItemExtras, self::ROW_FEE_TYPE);
        $isSame = $this->compareExtras($databaseFeeExtras, $backofficeFeeExtras);
        if (!$isSame) {
            throw new OrderDifferenceException('Fee order items did not match', $databaseOrderItemExtras, $backofficeOrderItemExtras);
        }
        // End comparing fee order items

        // Start comparing shipping order items
        $databaseShippingExtras = $this->filterExtrasByRowExtraType($databaseOrderItemExtras, self::ROW_SHIPPING_METHOD_TYPE);
        $backofficeShippingExtras = $this->filterExtrasByRowExtraType($backofficeOrderItemExtras, self::ROW_SHIPPING_METHOD_TYPE);
        $isSame = $this->compareExtras($databaseShippingExtras, $backofficeShippingExtras);
        if (!$isSame) {
            // Try comparing each shipping methods without taxes

            // Reset indexes first
            $databaseShippingExtras = array_values($databaseShippingExtras);

            // Get all shipping order items without taxes on md5
            $databaseShippingOrderItemsWithoutTaxes = $this->getShippingOrderItems($wcOrder, true);
            $databaseShippingOrderItemExtrasWithoutTaxes = array_column($databaseShippingOrderItemsWithoutTaxes, 'extra');
            foreach ($backofficeShippingExtras as $backofficeShippingExtra) {
                // Compare every single extra by its ID
                ksort($backofficeShippingExtra);

                $backofficeRowId = $backofficeShippingExtra[self::EXTRA_ROW_ID_KEY];
                // Find the extra with the same row ID from backoffice extras
                $databaseShippingExtraMaybeWithTaxIndex = array_search($backofficeRowId, array_column($databaseShippingExtras, self::EXTRA_ROW_ID_KEY), true);
                if (false === $databaseShippingExtraMaybeWithTaxIndex) {
                    throw new OrderDifferenceException('Shipping order item does not exist on the initially sent extras', $databaseOrderItemExtras, $backofficeOrderItemExtras);
                }

                $databaseShippingExtraMaybeWithTax = $databaseShippingExtras[$databaseShippingExtraMaybeWithTaxIndex];
                ksort($databaseShippingExtraMaybeWithTax);
                // Compare the extra we assume that has tax to the backoffice extra
                if ($databaseShippingExtraMaybeWithTax !== $backofficeShippingExtra) {
                    // Find the extra with the same row ID from backoffice extras
                    $databaseShippingExtraWithoutTaxIndex = array_search($backofficeRowId, array_column($databaseShippingOrderItemExtrasWithoutTaxes, self::EXTRA_ROW_ID_KEY), true);
                    $databaseShippingExtraWithoutTax = $databaseShippingOrderItemExtrasWithoutTaxes[$databaseShippingExtraWithoutTaxIndex];
                    ksort($databaseShippingExtraWithoutTax);

                    // Compare the extra that has no tax to the backoffice extra
                    if ($databaseShippingExtraWithoutTax !== $backofficeShippingExtra) {
                        throw new OrderDifferenceException('Shipping order items did not match even without taxes comparison', $databaseOrderItemExtras, $backofficeOrderItemExtras);
                    }
                }
            }
        }
        // End comparing shipping order items
    }

    private function compareExtras(array $databaseExtras, array $backofficeExtras): bool
    {
        if (count($databaseExtras) !== count($backofficeExtras)) {
            return false;
        }
        foreach ($databaseExtras as &$extras) {
            ksort($extras);
        }
        unset($extras);

        foreach ($backofficeExtras as &$extras) {
            ksort($extras);
        }
        unset($extras);

        sort($databaseExtras);
        sort($backofficeExtras);

        for ($extrasCounter = 0, $backofficeOrderItemExtraCount = count($backofficeExtras); $extrasCounter < $backofficeOrderItemExtraCount; ++$extrasCounter) {
            $backofficeOrderItemExtra = $backofficeExtras[$extrasCounter];
            $databaseOrderItemExtra = $databaseExtras[$extrasCounter];
            if ($databaseOrderItemExtra !== $backofficeOrderItemExtra) {
                return false;
            }
        }

        return true;
    }

    private function filterExtrasByRowExtraType(array $orderItemExtras, string $rowType): array
    {
        return array_filter($orderItemExtras, static function ($orderItemExtra) use ($rowType) {
            return $orderItemExtra[self::EXTRA_ROW_TYPE] === $rowType;
        });
    }

    private function cleanExtras(array &$extras): void
    {
        foreach ($extras as &$extra) {
            foreach ($extra as $key => $data) {
                if (!in_array($key, self::EXTRA_KEYS_FOR_MD5_COMPARE, true)) {
                    unset($extra[$key]);
                }
            }
        }
    }

    /**
     * @throws OrderDifferenceException
     */
    private function checkOrderDifferenceBySet(array $databaseOrderItems, array $backofficeOrderItems): void
    {
        $databaseSets = [];
        foreach ($databaseOrderItems as $databaseOrderItem) {
            $databaseSets[] = (
                (int) $databaseOrderItem['quantity']).'|'
                .round($databaseOrderItem['ppu_wt'], 2).'|'
                .$databaseOrderItem['sku'];
        }

        $backofficeSets = [];
        foreach ($backofficeOrderItems as $backofficeOrderItem) {
            $backofficeSets[] = (
                (int) $backofficeOrderItem['quantity']).'|'
                .round($backofficeOrderItem['ppu_wt'], 2).'|'
                .$backofficeOrderItem['sku'];
        }

        sort($databaseSets);
        sort($backofficeSets);

        for ($setCounter = 0, $backofficeSetCount = count($backofficeSets); $setCounter < $backofficeSetCount; ++$setCounter) {
            $backofficeSet = $backofficeSets[$setCounter];
            $databaseSet = $databaseSets[$setCounter];
            if ($databaseSet !== $backofficeSet) {
                throw new OrderDifferenceException("Set did not match, Backoffice set {$backofficeSet} and Shop set {$databaseSet}");
            }
        }
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
                self::STATUS_REFUNDED === $storekeeper_status
                || self::STATUS_CANCELLED === $storekeeper_status
            ) {
                return false;
            }

            if (
                self::STATUS_COMPLETE === $storekeeper_status
                && self::STATUS_REFUNDED !== $woocommerce_status
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $order WC_Order
     *
     * @throws \Exception
     */
    private function getOrderItems(\WC_Order $order): array
    {
        $orderItems = [];
        $this->debug('Adding product items');
        $productFactory = new \WC_Product_Factory();

        $orderMetadata = $order->get_meta_data();

        $shopProductIdMap = [];
        foreach ($orderMetadata as $metadata) {
            $data = $metadata->get_data();
            if (OrderHandler::SHOP_PRODUCT_ID_MAP === $data['key']) {
                $shopProductIdMap = $data['value'];
            }
        }

        $orderItemIds = [];
        $orderItemIndex = 0;

        /**
         * @var $orderProduct WC_Order_Item_Product
         */
        foreach ($order->get_items() as $index => $orderItemProduct) {
            $this->debug('Adding product item', [
                'name' => $orderItemProduct->get_name(),
                'order_id' => $orderItemProduct->get_order_id(),
                'quantity' => $orderItemProduct->get_quantity(),
            ]);

            $diff_product = null;
            $currentProduct = $this->getProductForOrderLine($orderItemProduct, $productFactory);

            $quantity = $orderItemProduct->get_quantity(self::CONTEXT);
            if (is_null($currentProduct)) {
                $data = [
                    'isSkipped' => true,
                    'quantity' => $quantity,
                    'name' => $orderItemProduct->get_name(self::CONTEXT),
                ];
                $this->debug($index.' Woocommerce product object can\'t be found, it may have been deleted and this will be skipped during checking', [
                    'quantity' => $quantity,
                    'name' => $orderItemProduct->get_name(self::CONTEXT),
                ]);
            } else {
                $productId = $currentProduct->get_id();
                $shopProductId = $orderItemProduct->get_meta(self::CART_FIELD_SHOP_PRODUCT_ID);
                if (empty($shopProductId)) {
                    if (array_key_exists($productId, $shopProductIdMap)) {
                        $shopProductId = $shopProductIdMap[$productId];
                    } else {
                        $shopProductId = $this->fetchShopProductId($currentProduct);
                    }
                }

                $description = '';
                if ($currentProduct instanceof \WC_Product_Variation) {
                    $description = $currentProduct->get_attribute_summary();
                }

                $this->debug('Got product', $currentProduct);

                $ppu_wt = $order->get_item_total($orderItemProduct, true, false);
                $rounded_ppu_wt = NumberUtil::round($ppu_wt, wc_get_price_decimals());
                if ($rounded_ppu_wt != $ppu_wt) {
                    // StoreKeeper backend does not accept the prices which are rounded to 2 digits
                    // to make the sum right we will add and extra product, so the total order it correct
                    $total_diff = ($quantity * $ppu_wt) - ($quantity * $rounded_ppu_wt);
                    $total_diff = NumberUtil::round($total_diff, wc_get_price_decimals());

                    if ($total_diff < 0) {
                        $diff_product = [
                            'ppu_wt' => $total_diff,
                            'quantity' => 1,
                            'is_discount' => true,
                        ];
                    } elseif ($total_diff > 0) {
                        $diff_product = [
                            'ppu_wt' => $total_diff,
                            'quantity' => 1,
                            'is_payment' => true,
                        ];
                    }
                }
                $data = [
                    'sku' => $currentProduct ?
                        $currentProduct->get_sku(self::CONTEXT) :
                        $orderItemProduct->get_name(self::CONTEXT),
                    'ppu_wt' => $rounded_ppu_wt, // get price with discount
                    'before_discount_ppu_wt' => $order->get_item_subtotal($orderItemProduct, true, false), // get without discount
                    'quantity' => $quantity,
                    'name' => $orderItemProduct->get_name(self::CONTEXT),
                    'description' => $description,
                    'shop_product_id' => $shopProductId,
                ];

                // use the current products as fallback
                if (empty($data['name'])) {
                    $data['name'] = $currentProduct->get_name(self::CONTEXT);
                }

                $extra = [
                    self::EXTRA_ROW_ID_KEY => $orderItemProduct->get_id(),
                    self::EXTRA_ROW_TYPE => self::ROW_PRODUCT_TYPE,
                    self::EXTRA_ROW_META => $this->getOrderLineMeta($orderItemProduct),
                ];

                $data['extra'] = $extra;
            }

            $this->addDataToOrderItems($orderItemProduct, $orderItemIndex, $orderItemIds, $data, $orderItems);

            if (!is_null($diff_product)) {
                $diff_product['name'] = $data['name'];
                $diff_product['sku'] = $data['sku'];
                $diff_product['shop_product_id'] = $data['shop_product_id'];
                $diff_product['extra'] = [
                    self::EXTRA_ROW_ID_KEY => $orderItemProduct->get_id(),
                    self::EXTRA_ROW_TYPE => self::ROW_PRODUCT_DIFF_TYPE,
                ];
                $orderItems[] = $diff_product;
                $this->debug($index.' Added diff product item', $diff_product);
            }
        }

        $this->debug('Added product items', ['count' => count($orderItems)]);

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

            if ($fee->meta_exists(self::EMBALLAGE_TAX_RATE_ID_META_KEY)) {
                $data['tax_rate_id'] = $fee->get_meta(self::EMBALLAGE_TAX_RATE_ID_META_KEY);
            }

            $extra = [
                self::EXTRA_ROW_ID_KEY => $fee->get_id(),
                self::EXTRA_ROW_MD5_KEY => md5(json_encode($fee->get_data(), JSON_THROW_ON_ERROR)),
                self::EXTRA_ROW_TYPE => self::ROW_FEE_TYPE,
            ];

            $data['extra'] = $extra;
            $orderItems[] = $data;

            $this->debug('Added fee item', $data);
        }

        $shippingMethodOrderItems = $this->getShippingOrderItems($order);
        $orderItems = array_merge($orderItems, $shippingMethodOrderItems);
        $this->debug('Added shipping items');

        return $orderItems;
    }

    public function getShippingOrderItems(\WC_Order $order, bool $excludeTaxesTotalOnMd5 = false): array
    {
        $orderItems = [];
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

            $shippingMethodData = $shipping_method->get_data();
            if ($excludeTaxesTotalOnMd5 || !$this->already_exported()) {
                $shippingMethodData['taxes']['total'] = [];
            }

            $extra = [
                self::EXTRA_ROW_ID_KEY => $shipping_method->get_id(),
                self::EXTRA_ROW_MD5_KEY => md5(json_encode($shippingMethodData, JSON_THROW_ON_ERROR)),
                self::EXTRA_ROW_TYPE => self::ROW_SHIPPING_METHOD_TYPE,
            ];

            $data['extra'] = $extra;

            $orderItems[] = $data;
        }

        return $orderItems;
    }

    private function fetchShopProductId(\WC_Product $product): ?int
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

    protected function convertKnownGeneralException(GeneralException $throwable): \Throwable
    {
        if ('ShopModule::OrderDuplicateNumber' === $throwable->getApiExceptionClass()) {
            return new ExportException(
                esc_html__('Order with this order number already exists. Tried duplicate up to '.$this->shopOrderId.'('.self::MAXIMUM_DUPLICATE_COUNT.')', I18N::DOMAIN),
                $throwable->getCode(),
                $throwable
            );
        }

        return parent::convertKnownGeneralException($throwable);
    }

    protected function processPaymentsAndRefunds(\WC_Order $WpObject, int $storekeeper_id): void
    {
        $shopModule = $this->storekeeper_api->getModule('ShopModule');
        $storekeeper_order = $shopModule->getOrder($storekeeper_id, null);

        $woocommerceOrderId = $WpObject->get_id();
        $this->processPayments($WpObject, $storekeeper_id, $storekeeper_order);
        $this->processRefunds($woocommerceOrderId, $storekeeper_id);
    }

    protected function processPayments(\WC_Order $WpObject, int $storekeeper_id, array $storekeeperOrder): void
    {
        $isPaidInBackoffice = (bool) $storekeeperOrder['is_paid'];
        $order_id = $WpObject->get_id();

        if (PaymentModel::orderHasPayment($order_id)) {
            $paymentsToSync = PaymentModel::findOrderPaymentsNotInSync($order_id);
            foreach ($paymentsToSync as $payment) {
                if (!empty($payment['payment_id'])) {
                    $gateway_payment_id = $payment['payment_id'];
                    $this->debug(
                        'Attaching payment to order',
                        ['order_id' => $storekeeper_id, 'payment_id' => $gateway_payment_id]
                    );
                    $this->syncPaymentToBackend($gateway_payment_id, $storekeeper_id);
                }
                PaymentModel::markPaymentAsSynced($payment);
            }
        } else {
            // no sk payment yes at this point

            $wooCommerceStatus = $WpObject->get_status();
            // Either the order is in paid statuses, or refunded but has payment
            if ($WpObject->is_paid() || (self::STATUS_REFUNDED === $wooCommerceStatus && $WpObject->get_date_paid())) {
                /*
                 * WP order is paid and has not PaymentGateway payment.
                 * Example: the order was paid using the PayNL plugin
                 */
                $this->debug('Backend payment state of this order (wp order is paid)', [
                    'storekeeper_order_is_paid' => $isPaidInBackoffice,
                    'order_id' => $order_id,
                ]);

                // Order paid in WP but not in the Backoffice.
                if (!$isPaidInBackoffice) {
                    $paymentId = $this->newSkPaymentForWcPayment($WpObject);

                    $id = PaymentModel::addPayment($order_id, $paymentId, $WpObject->get_total(), true);
                    $this->syncPaymentToBackend($paymentId, $storekeeper_id);
                    PaymentModel::markIdAsSynced($id);

                    $this->debug('The order is paid: Marked the order as paid. Payment_id='.$paymentId);
                } else {
                    $this->debug('Did not mark the order as paid as it is already paid in backoffice');
                }
            } else {
                $this->debug('Did not mark the order as paid since it was not needed');
            }
        }
    }

    /**
     * @throws \Throwable
     */
    protected function processRefunds($woocommerceOrderId, $storekeeperId): void
    {
        // Attach refunds to order
        if (PaymentGateway::hasUnsyncedRefunds($woocommerceOrderId)) {
            $refundPayments = PaymentGateway::getUnsyncedRefundsPaymentIds($woocommerceOrderId);

            foreach ($refundPayments as $refundPayment) {
                $refundPaymentId = $refundPayment['sk_refund_id'];
                $refundId = $refundPayment['wc_refund_id'];
                $this->debug(
                    'Attaching payment refund to order',
                    ['order_id' => $storekeeperId, 'payment_id' => $refundPaymentId]
                );

                $this->syncPaymentToBackend($refundPaymentId, $storekeeperId);
                PaymentGateway::markRefundAsSynced($woocommerceOrderId, $refundPaymentId, $refundId);
            }
        }

        $unsyncedRefundsWithoutIds = PaymentGateway::getUnsyncedRefundsWithoutPaymentIds($woocommerceOrderId);
        if (count($unsyncedRefundsWithoutIds) > 0) {
            foreach ($unsyncedRefundsWithoutIds as $unsyncedRefundsWithoutId) {
                try {
                    $this->doProcessRefundsWithoutIds($unsyncedRefundsWithoutId, $woocommerceOrderId, $storekeeperId);
                } catch (\Throwable $exception) {
                    $this->debug('Failed to refund order', [
                        'order_id' => $storekeeperId,
                        'error' => $exception->getMessage(),
                        'unsyncedRefundsWithoutId' => $unsyncedRefundsWithoutId,
                    ]);

                    throw $exception;
                }
            }
        }
    }

    /**
     * @throws \Throwable
     */
    protected function doProcessRefundsWithoutIds($unsyncedRefundsWithoutId, $woocommerceOrderId, $storekeeperId): void
    {
        $shopModule = $this->storekeeper_api->getModule('ShopModule');

        $id = $unsyncedRefundsWithoutId['id'];
        $refundId = $unsyncedRefundsWithoutId['wc_refund_id'];
        $refundAmount = $unsyncedRefundsWithoutId['amount'];
        $roundedAmount = round(-abs($refundAmount), 2);
        if (0.00 === $roundedAmount) {
            // Just mark the refund as synced as it is 0 anyway
            $this->debug('Refund will be marked as synced since the amount is 0', [
                'refundId' => $refundId,
                'woocommerceOrderId' => $woocommerceOrderId,
            ]);
            PaymentGateway::markRefundAsSynced($woocommerceOrderId, null, $refundId);
        } else {
            if (PaymentModel::orderHasPayment($woocommerceOrderId)) {
                try {
                    $storekeeperPayment = $this->getPaymentForRefund($woocommerceOrderId);
                    $storekeeperRefundId = $shopModule->refundAllOrderItems([
                        'id' => $storekeeperId,
                        'refund_payments' => [
                            [
                                'payment_id' => $storekeeperPayment['payment_id'],
                                'amount' => $roundedAmount,
                                'description' => sprintf(
                                    __('Refund via Wordpress plugin (Refund #%s)', I18N::DOMAIN),
                                    $refundId
                                ),
                            ],
                        ],
                    ]);
                    $this->debug('Storekeeper refund was created', [
                        'payment' => $storekeeperPayment,
                        'refund_id' => $storekeeperRefundId,
                    ]);
                    PaymentGateway::updateRefund($id, $storekeeperRefundId, $refundAmount);
                } catch (GeneralException $generalException) {
                    if ('Only invoiced orders can be refunded' === $generalException->getMessage()) {
                        $storekeeperRefundId = PaymentGateway::createRefundAsPayment($refundId, $refundAmount);
                        $this->debug('Storekeeper refund was created', [
                            'order_id' => $storekeeperId,
                            'payment_id' => $storekeeperRefundId,
                        ]);
                        PaymentGateway::updateRefund($id, $storekeeperRefundId, $refundAmount);
                        $this->syncPaymentToBackend($storekeeperRefundId, $storekeeperId);
                    } else {
                        throw $generalException;
                    }
                }
            } else {
                $storekeeperRefundId = PaymentGateway::createRefundAsPayment($refundId, $refundAmount);
                $this->debug('Storekeeper refund was created', [
                    'order_id' => $storekeeperId,
                    'payment_id' => $storekeeperRefundId,
                ]);
                PaymentGateway::updateRefund($id, $storekeeperRefundId, $refundAmount);
                $this->syncPaymentToBackend($storekeeperRefundId, $storekeeperId);
            }
            PaymentGateway::markRefundAsSynced($woocommerceOrderId, $storekeeperRefundId, $refundId);
        }
    }

    public static function splitStreetNumber(string $streetNumber): array
    {
        if (preg_match('/^\s*(?P<streetnumber>\d*+)\s*[\-\/]?\s*(?P<flatnumber>[A-Za-z\d]+|[A-Za-z\d\-\s*]+)$/i', $streetNumber, $matches)) {
            return [
                'streetnumber' => $matches['streetnumber'],
                'flatnumber' => $matches['flatnumber'],
            ];
        }

        return [
            'streetnumber' => $streetNumber,
            'flatnumber' => '',
        ];
    }

    protected function getProductForOrderLine(\WC_Order_Item $orderItemProduct, \WC_Product_Factory $productFactory): ?\WC_Product
    {
        $variationProductId = $orderItemProduct->get_variation_id();
        $isVariation = $variationProductId > 0; // Variation_id is 0 by default, if it is any other, its a variation products;
        if ($isVariation) {
            $productId = $variationProductId;
            $currentProduct = $productFactory->get_product($productId);
        } else {
            $productId = $orderItemProduct->get_product_id();

            // not a variation but might be variable due to gui bug
            $currentProduct = $productFactory->get_product($productId);
            if ($currentProduct instanceof \WC_Product_Variable) {
                $variations = $currentProduct->get_visible_children();
                $count = count($variations);
                if (1 === $count) {
                    $productId = array_pop($variations);
                } else {
                    throw new ExportException('Order contains a variable product '.$currentProduct->get_name().' with '.$count.' variations. Fixed the order manually in woocommerce to a correct product.');
                }
                $currentProduct = $productFactory->get_product($productId);
            }
        }
        if (false === $currentProduct) {
            return null;
        }

        return $currentProduct;
    }

    protected function isAlreadyLinkedError($generalException): bool
    {
        return false !== strpos($generalException->getMessage(), 'This payment is already linked');
    }

    protected function syncPaymentToBackend($storekeeperPaymentId, $storekeeperOrderId): void
    {
        $shopModule = $this->storekeeper_api->getModule('ShopModule');
        try {
            $shopModule->attachPaymentIdsToOrder(['payment_ids' => [$storekeeperPaymentId]], $storekeeperOrderId);
        } catch (GeneralException $generalException) {
            // Check if the payment is already attached to the order, we can ignore it an move on.
            if (!$this->isAlreadyLinkedError($generalException)) {
                throw $generalException;
            }
        } catch (\Throwable $exception) {
            $this->debug('Failed to attach payment to order', [
                'order_id' => $storekeeperOrderId,
                'payment_id' => $storekeeperPaymentId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    protected function newSkPaymentForWcPayment(\WC_Order $WpObject): int
    {
        $PaymentModule = $this->storekeeper_api->getModule('PaymentModule');

        $paymentGateway = wc_get_payment_gateway_by_order($WpObject);
        if ($paymentGateway) {
            $paymentGatewayTitle = $paymentGateway->get_method_title();
            $comment = $paymentGatewayTitle.' ('.__('Wordpress plugin', I18N::DOMAIN).')';
        } else {
            $comment = ucwords(str_replace('pay_gateway_', '', $WpObject->get_payment_method()));
        }

        if (!empty($WpObject->get_meta('transactionId'))) {
            $comment .= ' #'.$WpObject->get_meta('transactionId');
        } elseif (!empty($WpObject->get_transaction_id())) {
            $comment .= ' #'.$WpObject->get_transaction_id();
        }

        return $PaymentModule->newWebPayment([
            'amount' => $WpObject->get_total(),
            'description' => $comment,
        ]);
    }

    /**
     * get first paid payment, if no payment found it will take any payment.
     *
     * @return mixed|null
     */
    protected function getPaymentForRefund(int $woocommerceOrderId): ?array
    {
        $storekeeperPayment = null;
        $storekeeperPayments = PaymentModel::findOrderPayments($woocommerceOrderId);
        foreach ($storekeeperPayments as $payment) {
            if (is_null($storekeeperPayment)) {
                $storekeeperPayment = $payment;
            }
            if ($payment['is_paid']) {
                break;
            }
        }

        return $storekeeperPayment;
    }

    protected function addDataToOrderItems(
        \WC_Order_Item $wcItemProduct,
        int &$orderItemIndex,
        array &$orderItemIds,
        array $orderItem,
        array &$orderItems
    ): void {
        $added_to_items = false;
        $orderItemId = $wcItemProduct->get_meta(self::CART_FIELD_ID);
        if (!empty($orderItemId)) {
            $orderItemIds[$orderItemId] = $orderItemIndex;

            $orderItemParentId = $wcItemProduct->get_meta(self::CART_FIELD_PARENT_ID);
            $addonGroupId = $wcItemProduct->get_meta(self::CART_FIELD_ADDON_GROUP_ID);
            if (!empty($addonGroupId)) {
                $orderItem['extra']['product_addon_group_id'] = $addonGroupId;
            }
            if (array_key_exists($orderItemParentId, $orderItemIds)) {
                $parent = &$orderItems[$orderItemIds[$orderItemParentId]];
                if (!isset($parent['subitems'])) {
                    $parent['subitems'] = [];
                }

                $orderItem['extra'][self::EXTRA_ROW_MD5_KEY] = $this->calculateRowMd5($orderItem);
                $parent['subitems'][] = $orderItem;
                $added_to_items = true;
            }
        }

        if (!$added_to_items) {
            $orderItem['extra'][self::EXTRA_ROW_MD5_KEY] = $this->calculateRowMd5($orderItem);
            $orderItems[$orderItemIndex] = $orderItem;
        }
        ++$orderItemIndex;
        $this->debug($orderItemIndex.' Added product item', $orderItem);
    }

    protected function getOrderLineMeta(\WC_Order_Item $orderItemProduct): array
    {
        $metaData = $orderItemProduct->get_meta_data();
        $meta = [];
        foreach ($metaData as $metaDatum) {
            /**
             * @var $meta_datum WC_Meta_Data
             *                  This is the metadata that is set on the product order, for a variation product that.
             *                  More info about meta_data: https://docs.woocommerce.com/wc-apidocs/class-WC_Data.html
             */
            $meta_data = $metaDatum->get_data();
            $meta[$meta_data['key']] = $meta_data['value'];
        }

        return $meta;
    }

    protected function calculateRowMd5(array $orderItem): string
    {
        if (!empty($orderItem['extra'])) {
            ksort($orderItem['extra']);
            $valid = [];
            foreach ($orderItem['extra'] as $key => $data) {
                if (in_array($key, self::EXTRA_KEYS_FOR_MD5_COMPARE, true)) {
                    $valid[] = $data;
                }
            }
            $orderItem['extra'] = $valid;
        }

        return md5(json_encode($orderItem, JSON_THROW_ON_ERROR));
    }
}
