<?php

namespace StoreKeeper\WooCommerce\B2C\Helpers\Location;

use StoreKeeper\WooCommerce\B2C\Models\Location\AddressModel;

class AddressHelper
{

    /**
     * Get formatted location address
     *
     * @param int|array|object $locationAddress
     * @param string $separator 
     * @return string
     */
    public static function getFormattedAddress($locationAddress, $separator = '<br/>')
    {
        $wcAddress = self::convertToWcAddress($locationAddress);

        if ($wcAddress) {
            return WC()->countries->get_formatted_address($wcAddress, $separator);
        }

        return '';
    }

    /**
     * Converts location address to WooCommerce address
     *
     * @param int|array|object $locationAddress
     * @return bool|array
     */
    public static function convertToWcAddress($locationAddress)
    {
        if (is_numeric($locationAddress)) {
            $locationAddress = AddressModel::get($locationAddress);
        }

        if (is_object($locationAddress) && ($locationAddress instanceof \stdClass ||
            method_exists($locationAddress, '__toArray'))) {
            $locationAddress = (array) $locationAddress;
        }

        try {
            if (!is_array($locationAddress)) {
                throw new \Exception('Invalid location address');
            }

            AddressModel::validateData($locationAddress);

            return [
                'city' => $locationAddress['city'] ?? '',
                'state' => $locationAddress['state'] ?? '',
                'postcode' => $locationAddress['zipcode'] ?? '',
                'country' => $locationAddress['country'] ?? '',
                'address_1' => trim(
                    implode(
                        ' ',
                        [
                            $locationAddress['street'] ?? '',
                            $locationAddress['streetnumber'] ?? '',
                            $locationAddress['flatnumber'] ?? ''
                        ]
                    )
                )
            ];
        } catch (\Exception $e) {
            return false;
        }
    }
}
