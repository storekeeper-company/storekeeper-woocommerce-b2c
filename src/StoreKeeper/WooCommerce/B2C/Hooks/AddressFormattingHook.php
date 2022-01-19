<?php

namespace StoreKeeper\WooCommerce\B2C\Hooks;

use Exception;

class AddressFormattingHook
{
    /**
     * Add the house number to NL country's address displaying format.
     */
    public function customAddressFormats(array $formats): array
    {
        $formats['NL'] = "{company}\n{name}\n{address_house_number} {address_1}\n{address_2}\n{postcode} {city}\n{country}";

        return $formats;
    }

    /**
     * Add the house number to NL country's address variables to replace the placeholders in the format.
     */
    public function customAddressReplacements(array $replacements, array $arguments): array
    {
        $replacements['{address_house_number}'] = $arguments['address_house_number'] ?? '';

        return $replacements;
    }

    /**
     * Add house number to raw address.
     *
     * @throws Exception
     */
    public function addCustomAddressArguments(array $address, int $customerId, string $addressType): array
    {
        return $this->getAddressWithCustomFields($customerId, $addressType, $address);
    }

    /**
     * Add house number to raw address when displaying on order.
     *
     * @throws Exception
     */
    public function addCustomAddressArgumentsForOrder(array $address, string $addressType, \WC_Order $order): array
    {
        $customerId = $order->get_customer_id();

        return $this->getAddressWithCustomFields($customerId, $addressType, $address);
    }

    /**
     * @throws Exception
     */
    protected function getAddressWithCustomFields(int $customerId, string $addressType, array $address): array
    {
        if (0 !== $customerId) {
            $customer = new \WC_Customer($customerId);
            $houseNumber = $customer->get_meta($addressType.'_address_house_number', true);
            if (!empty($houseNumber)) {
                $address['address_house_number'] = $houseNumber;
            } else {
                $address['address_house_number'] = '';
            }
        }

        return $address;
    }
}
