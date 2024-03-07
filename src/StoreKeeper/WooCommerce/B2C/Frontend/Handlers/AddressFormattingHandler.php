<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

class AddressFormattingHandler
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
     * @throws \Exception
     */
    public function addCustomAddressArguments(array $address, int $customerId, string $addressType): array
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

    /**
     * Add house number to raw address when displaying on order.
     *
     * @throws \Exception
     */
    public function addCustomAddressArgumentsForOrder(array $address, string $addressType, \WC_Order $order): array
    {
        $houseNumber = $order->get_meta($addressType.'_address_house_number', true);
        if (!empty($houseNumber)) {
            $address['address_house_number'] = $houseNumber;
        } else {
            $address['address_house_number'] = '';
        }

        return $address;
    }
}
