<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Exports;

use WC_Helper_Coupon;
use WC_Helper_Order;

abstract class AbstractOrderExportTest extends AbstractExportTest
{
    public function emptyEnvironment()
    {
        $wc_orders = wc_get_orders([]);
        $this->assertEquals(0, count($wc_orders), 'Test was not ran in an empty environment');
    }

    /**
     * Creates a WooCommerce order, where the $additional_props is being set on the order.
     *
     * @param array $additional_props
     *
     * @return int
     */
    public function createWooCommerceOrder($additional_props = [])
    {
        $order = WC_Helper_Order::create_order();
        $order->set_props($additional_props);

        $coupon = WC_Helper_Coupon::create_coupon();
        $coupon->set_amount('25');
        $coupon->set_discount_type('percent');
        $coupon->save();

        $order->apply_coupon($coupon);
        $order->save();

        return $order->save();
    }

    protected function getOrderProps(bool $isNlCountry = false): array
    {
        $faker = $this->faker;
        $vals = [
            'billing_email' => $faker->email,
            'billing_phone' => $faker->phoneNumber,
            'customer_note' => $faker->sentence,
        ];
        $order = [];
        foreach ($vals as $k => $val) {
            $order[$k] = $val;
        }
        $order += $this->getAddress('billing_', $isNlCountry);
        $order += $this->getAddress('shipping_', $isNlCountry);

        return $order;
    }

    protected function getAddress($prefix, bool $isNlCountry = false): array
    {
        $faker = $this->faker;
        $vals = [
            'first_name' => $faker->firstName,
            'last_name' => $faker->lastName,
            'company' => $faker->company,
            'address_1' => $faker->streetAddress,
            'address_2' => $faker->streetSuffix,
            'city' => $faker->city,
            'postcode' => $faker->postcode,
            'country' => $isNlCountry ? 'NL' : $faker->countryCode,
            'state' => $faker->state,
        ];

        $houseNumber = $faker->randomNumber();
        if ($isNlCountry) {
            $vals['address_house_number'] = $houseNumber;
        }

        $address = [];
        foreach ($vals as $k => $val) {
            $address[$prefix.$k] = $val;
        }

        return $address;
    }
}
