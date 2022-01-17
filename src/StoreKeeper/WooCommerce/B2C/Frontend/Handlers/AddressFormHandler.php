<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Endpoints\EndpointLoader;
use StoreKeeper\WooCommerce\B2C\Endpoints\WebService\AddressSearchEndpoint;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\AbstractOptions;

class AddressFormHandler
{
    public const STREET_ADDRESS_POSITION = 4;
    public const HOUSE_NUMBER_FIELD = 'address_house_number';
    public const DEFAULT_ADDRESS_TYPES = ['shipping', 'billing'];

    public function addCheckoutScripts(): void
    {
        if (is_checkout()) {
            $this->enqueueScriptsAndStyles(null);
        }
    }

    public function enqueueScriptsAndStyles($addressType): void
    {
        $storekeeperAddressFormHandle = AbstractOptions::PREFIX.'-address-form';
        wp_enqueue_script($storekeeperAddressFormHandle, plugin_dir_url(__FILE__).'../assets/storekeeper-address-form.js');
        wp_enqueue_style($storekeeperAddressFormHandle, plugin_dir_url(__FILE__).'../assets/storekeeper-address-form.css');
        wp_localize_script($storekeeperAddressFormHandle, 'settings',
            [
                'url' => rest_url(EndpointLoader::getFullNamespace().'/'.AddressSearchEndpoint::ROUTE),
                'addressType' => $addressType,
                'defaultAddressTypes' => self::DEFAULT_ADDRESS_TYPES,
                'translations' => [
                    'Validating postcode and house number. Please wait...' => esc_html__('Validating postcode and house number. Please wait...', I18N::DOMAIN),
                    'Valid postcode and house number' => esc_html__('Valid postcode and house number', I18N::DOMAIN),
                    'Invalid postcode or house number' => esc_html__('Invalid postcode or house number', I18N::DOMAIN),
                    'Postcode format for NL address is invalid' => esc_html__('Postcode format for NL address is invalid', I18N::DOMAIN),
                ],
            ]);
    }

    public function alterAddressForm(array $fields): array
    {
        return $this->addFields($fields);
    }

    public function customLocale(array $locale): array
    {
        $locale['NL'][self::HOUSE_NUMBER_FIELD] = [
            'required' => true,
            'hidden' => false,
        ];

        $locale['NL']['postcode']['priority'] = 45;
        $locale['NL']['postcode']['label'] = __('Postcode / ZIP & House number', I18N::DOMAIN);
        $locale['NL']['postcode']['placeholder'] = __('Postcode / ZIP', I18N::DOMAIN);

        $locale['NL']['address_1'] = [
            'placeholder' => __('Street name', I18N::DOMAIN),
            'priority' => 55,
        ];

        return $locale;
    }

    public function customSelectors(array $fieldSelectors): array
    {
        $fieldSelectors[self::HOUSE_NUMBER_FIELD] = '#billing_'.self::HOUSE_NUMBER_FIELD.'_field, #shipping_'.self::HOUSE_NUMBER_FIELD.'_field';

        return $fieldSelectors;
    }

    public function customAddressFormats(array $formats): array
    {
        $formats['NL'] = "{company}\n{name}\n{address_house_number} {address_1}\n{address_2}\n{postcode} {city}\n{country}";

        return $formats;
    }

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

    public function customAddressReplacements(array $replacements, array $arguments): array
    {
        $replacements['{address_house_number}'] = $arguments['address_house_number'] ?? '';

        return $replacements;
    }

    protected function validateStreet(string $addressType, array $inputs): void
    {
        $countryKey = $addressType.'_country';

        if (isset($inputs[$countryKey]) && AddressSearchEndpoint::DEFAULT_COUNTRY_ISO === $inputs[$countryKey]) {
            try {
                $postCodeKey = $addressType.'_postcode';
                $houseNumberKey = $addressType.'_address_house_number';

                if (isset($inputs[$postCodeKey])) {
                    $postCode = $inputs[$postCodeKey];
                }

                if (isset($inputs[$houseNumberKey])) {
                    $houseNumber = $inputs[$houseNumberKey];
                }
                AddressSearchEndpoint::validateAddress($postCode, $houseNumber);
            } catch (\Throwable $throwable) {
                wc_add_notice(sprintf(__('Invalid %s postcode or house number', I18N::DOMAIN), $addressType), 'error');
            }
        }
    }

    /**
     * Validate postcode and house number if country is NL. Regex is already handled by WooCommerce validation.
     *
     * @see https://woocommerce.github.io/code-reference/files/woocommerce-includes-class-wc-validation.html | Line 109
     */
    public function validateCustomFieldsForCheckout(): void
    {
        $inputs = $_POST;
        foreach (self::DEFAULT_ADDRESS_TYPES as $addressType) {
            $this->validateStreet($addressType, $inputs);
        }
    }

    /**
     * Validate postcode and house number if country is NL. Regex is already handled by WooCommerce validation.
     *
     * @see https://woocommerce.github.io/code-reference/files/woocommerce-includes-class-wc-validation.html | Line 109
     */
    public function validateCustomFields(int $userId, string $addressType): void
    {
        if ($userId <= 0) {
            return;
        }

        $inputs = $_POST;
        $this->validateStreet($addressType, $inputs);
    }

    private function addFields(array $fields)
    {
        $updatedFields = $this->insertFieldsAtPosition($fields, [
            self::HOUSE_NUMBER_FIELD => [
                'placeholder' => __('House number', I18N::DOMAIN),
                'required' => false,
                'type' => 'text',
                'priority' => 50,
                'hidden' => true,
                'class' => ['form-row-wide', 'address-field'],
                'label' => __('House number', I18N::DOMAIN),
                'label_class' => ['screen-reader-text'],
            ],
        ],
            self::STREET_ADDRESS_POSITION
        );

        return $updatedFields;
    }

    private function insertFieldsAtPosition(array $originalFields, array $newFields, int $position): array
    {
        $updatedFields = array_slice($originalFields, 0, $position, true);
        $updatedFields += $newFields;

        $updatedFields += array_slice($originalFields, $position, count($originalFields) - 1, true);

        return $updatedFields;
    }
}
