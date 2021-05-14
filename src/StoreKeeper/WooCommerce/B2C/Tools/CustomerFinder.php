<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\ApiWrapper\Exception\GeneralException;

class CustomerFinder
{
    const EDIT_CONTEXT = 'edit';

    /**
     * @param $email
     *
     * @return bool
     */
    public static function customerEmailIsKnownInBackend($email)
    {
        return (bool) self::findCustomerRelationDataIdByEmail($email);
    }

    public static function createCustomerFromWcOrder(\WC_Order $order)
    {
        // Get correct addressed
        $billingAddress = self::extractBillingAddressFromOrder($order, false);
        $shippingAddress = $billingAddress;
        if ($order->has_shipping_address()) {
            $shippingAddress = self::extractShippingAddressFromOrder($order, false);
        }

        // Setup call data
        $call_data = [
            'relation' => [
                'business_data' => self::extractBusinessDataFromOrder($order),
                'contact_person' => self::extractContactPersonFromOrder($order),
                'contact_set' => self::extractBillingContactSetFromOrder($order),
                'contact_address' => $shippingAddress,
                'address_billing' => $billingAddress,
                'subuser' => [
                    'login' => $order->get_billing_email(self::EDIT_CONTEXT),
                    'email' => $order->get_billing_email(self::EDIT_CONTEXT),
                ],
            ],
        ];

        // Create customer in backend
        $storekeeper_api = StoreKeeperApi::getApiByAuthName(StoreKeeperApi::SYNC_AUTH_DATA);
        $relation_data_id = $storekeeper_api->getModule('ShopModule')->newShopCustomer($call_data);

        // Update user meta
        update_user_meta($order->get_user_id(self::EDIT_CONTEXT), 'storekeeper_id', $relation_data_id);

        // Return user relation_data_id
        return (int) $relation_data_id;
    }

    /**
     * @param $email
     *
     * @return bool|int
     */
    public static function findCustomerRelationDataIdByEmail($email)
    {
        $id = false;
        if (!empty($email)) {
            try {
                $storekeeper_api = StoreKeeperApi::getApiByAuthName(StoreKeeperApi::SYNC_AUTH_DATA);
                $customer = $storekeeper_api->getModule('ShopModule')->findShopCustomerBySubuserEmail(
                    ['email' => $email]
                );
                $id = (int) $customer['id'];
            } catch (GeneralException $exception) {
                // Customer not found in the backend.
            }
        }

        return $id;
    }

    /**
     * @return bool|int
     */
    public static function ensureCustomerFromOrder(\WC_Order $order)
    {
        $email = $order->get_billing_email('edit');

        // Check if the customer already exists
        $relationDataId = self::findCustomerRelationDataIdByEmail($email);
        if ($relationDataId) {
            return $relationDataId;
        }

        // Else we are going to create the customer;
        return self::createCustomerFromWcOrder($order);
    }

    public static function extractBillingAddressFromOrder(\WC_Order $order, bool $bothAddresses = true): ?array
    {
        $street1 = trim($order->get_billing_address_1(self::EDIT_CONTEXT));
        $street2 = '';
        if ($bothAddresses) {
            $street2 = trim($order->get_billing_address_2(self::EDIT_CONTEXT));
        }

        return [
            'state' => $order->get_billing_state(self::EDIT_CONTEXT),
            'city' => $order->get_billing_city(self::EDIT_CONTEXT),
            'zipcode' => $order->get_billing_postcode(self::EDIT_CONTEXT),
            'street' => trim("$street1 $street2"),
            'country_iso2' => $order->get_billing_country(self::EDIT_CONTEXT),
            'name' => self::extractBillingNameFromOrder($order),
        ];
    }

    private static function extractShippingAddressFromOrder(\WC_Order $order, bool $bothAddresses = true): ?array
    {
        if ($order->has_shipping_address()) {
            $street1 = trim($order->get_shipping_address_1(self::EDIT_CONTEXT));
            $street2 = '';
            if ($bothAddresses) {
                $street2 = trim($order->get_shipping_address_2(self::EDIT_CONTEXT));
            }

            return [
                'state' => $order->get_shipping_state(self::EDIT_CONTEXT),
                'city' => $order->get_shipping_city(self::EDIT_CONTEXT),
                'zipcode' => $order->get_shipping_postcode(self::EDIT_CONTEXT),
                'street' => trim("$street1 $street2"),
                'country_iso2' => $order->get_shipping_country(self::EDIT_CONTEXT),
                'name' => self::extractShippingNameFromOrder($order),
            ];
        }

        return null;
    }

    private static function extractBillingContactSetFromOrder(\WC_Order $order, ?string $name = null): array
    {
        if (empty($name)) {
            $name = self::extractBillingNameFromOrder($order);
        }

        return [
            'email' => $order->get_billing_email(self::EDIT_CONTEXT),
            'phone' => $order->get_billing_phone(self::EDIT_CONTEXT),
            'name' => $name,
        ];
    }

    public static function extractBusinessDataFromOrder(\WC_Order $order): ?array
    {
        $companyName = $order->get_billing_company(self::EDIT_CONTEXT);
        if (!empty($companyName)) {
            return [
                'name' => $companyName,
                'country_iso2' => $order->get_billing_country(self::EDIT_CONTEXT),
            ];
        }

        return null;
    }

    public static function extractContactPersonFromOrder(\WC_Order $order)
    {
        return [
            'familyname' => $order->get_billing_last_name(self::EDIT_CONTEXT),
            'firstname' => $order->get_billing_first_name(self::EDIT_CONTEXT),
            'contact_set' => self::extractBillingContactSetFromOrder($order, $order->get_formatted_billing_full_name()),
        ];
    }

    public static function extractBillingNameFromOrder(\WC_Order $order): string
    {
        $companyName = $order->get_billing_company(self::EDIT_CONTEXT);
        if (empty($companyName)) {
            return $order->get_formatted_billing_full_name();
        }

        return $companyName;
    }

    public static function extractShippingNameFromOrder(\WC_Order $order): string
    {
        $companyName = $order->get_shipping_company(self::EDIT_CONTEXT);
        if (empty($companyName)) {
            return $order->get_formatted_shipping_full_name();
        }

        return $companyName;
    }

    public static function extractSnapshotDataFromOrder(\WC_Order $order)
    {
        // Get billing address
        $billingAddress = self::extractBillingAddressFromOrder($order);
        $billingContactSet = self::extractBillingContactSetFromOrder($order);
        $billingAddress['contact_set'] = $billingContactSet;

        // Get shipping address
        $shippingAddress = self::extractShippingAddressFromOrder($order);
        if ($shippingAddress) {
            $shippingAddress['contact_set'] = $billingContactSet; // WC does not has shipping specific email and phone
        } else {
            $shippingAddress = $billingAddress;
        }

        return [
            'business_data' => self::extractBusinessDataFromOrder($order),
            'contact_address' => $shippingAddress,
            'address_billing' => $billingAddress,
            'contact_set' => $billingContactSet,
            'contact_person' => self::extractContactPersonFromOrder($order),
            'name' => self::extractBillingNameFromOrder($order),
        ];
    }
}
