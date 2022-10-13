<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Endpoints\EndpointLoader;
use StoreKeeper\WooCommerce\B2C\Endpoints\WebService\AddressSearchEndpoint;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\AbstractOptions;

class AddressFormHandler
{
    public const STREET_ADDRESS_POSITION = 4;
    public const HOUSE_NUMBER_FIELD = 'address_house_number';

    public const SHIPPING_ADDRESS_TYPE = 'shipping';
    public const BILLING_ADDRESS_TYPE = 'billing';
    public const DEFAULT_ADDRESS_TYPES = [
        self::SHIPPING_ADDRESS_TYPE,
        self::BILLING_ADDRESS_TYPE,
    ];

    public const SHIPPING_HOUSE_NUMBER_KEY = self::SHIPPING_ADDRESS_TYPE.'_'.self::HOUSE_NUMBER_FIELD;
    public const BILLING_HOUSE_NUMBER_KEY = self::BILLING_ADDRESS_TYPE.'_'.self::HOUSE_NUMBER_FIELD;

    /**
     * Add javascript and css for postcode and house number validation on checkout page.
     */
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

    /**
     * Add house number field to the default address form to be used by NL country.
     */
    public function alterAddressForm(array $fields): array
    {
        $fields = $this->addFields($fields);
        $fields = $this->updateFields($fields);

        return $fields;
    }

    /**
     * Alter fields locale/options for NL.
     */
    public function customLocale(array $locale): array
    {
        $locale['NL'][self::HOUSE_NUMBER_FIELD] = [
            'required' => true,
            'hidden' => false,
        ];

        $locale['NL']['postcode']['priority'] = 45;
        $locale['NL']['postcode']['label'] = __('Postcode / ZIP', I18N::DOMAIN);
        $locale['NL']['postcode']['placeholder'] = __('Postcode / ZIP', I18N::DOMAIN);

        $locale['NL']['address_1'] = [
            'label' => __('Street address', I18N::DOMAIN),
            'placeholder' => __('Street name', I18N::DOMAIN),
            'priority' => 55,
        ];

        return $locale;
    }

    /**
     * Add the custom fields to the WooCommerce default selectors so that WooCommerce scripts can alter the display.
     */
    public function customSelectors(array $fieldSelectors): array
    {
        $fieldSelectors[self::HOUSE_NUMBER_FIELD] = '#'.self::BILLING_HOUSE_NUMBER_KEY.'_field, #'.self::SHIPPING_HOUSE_NUMBER_KEY.'_field';

        return $fieldSelectors;
    }

    protected function validateStreet(string $addressType): void
    {
        $countryKey = $addressType.'_country';

        if (isset($_POST[$countryKey]) && AddressSearchEndpoint::DEFAULT_COUNTRY_ISO === sanitize_text_field($_POST[$countryKey])) {
            try {
                $postCodeKey = $addressType.'_postcode';
                $houseNumberKey = $addressType.'_address_house_number';

                if (isset($_POST[$postCodeKey])) {
                    $postCode = sanitize_text_field($_POST[$postCodeKey]);
                }

                if (isset($_POST[$houseNumberKey])) {
                    $houseNumber = sanitize_text_field($_POST[$houseNumberKey]);
                }

                $splitStreet = OrderExport::splitStreetNumber($houseNumber);
                $streetNumber = $splitStreet['streetnumber'];
                AddressSearchEndpoint::validateAddress($postCode, $streetNumber);
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
        foreach (self::DEFAULT_ADDRESS_TYPES as $addressType) {
            if ('shipping' === $addressType && isset($_POST['ship_to_different_address']) && '1' === $_POST['ship_to_different_address']) {
                $this->validateStreet($addressType);
            } elseif ('shipping' === $addressType && !isset($_POST['ship_to_different_address'])) {
                // Some themes send the shipping fields even though
                // the ship_to_different_address is not checked/enabled
                $isShippingSubmitted = false;
                $fields = $_POST;
                foreach ($fields as $key => $field) {
                    if (str_starts_with(strtolower($key), 'shipping_')) {
                        $isShippingSubmitted = true;
                        break;
                    }
                }

                if (
                    isset($_POST[self::SHIPPING_HOUSE_NUMBER_KEY], $_POST[self::BILLING_HOUSE_NUMBER_KEY]) &&
                    $isShippingSubmitted &&
                    empty(sanitize_text_field($_POST[self::SHIPPING_HOUSE_NUMBER_KEY])) &&
                    !empty(sanitize_text_field($_POST[self::BILLING_HOUSE_NUMBER_KEY]))
                ) {
                    $_POST[self::SHIPPING_HOUSE_NUMBER_KEY] = sanitize_text_field($_POST[self::BILLING_HOUSE_NUMBER_KEY]);
                }

                $this->validateStreet($addressType);
            }
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

        $this->validateStreet($addressType);
    }

    /**
     * Save house number to order metadata be retrieved for displaying.
     */
    public function saveCustomFields(\WC_Order $order): void
    {
        if ($order->has_billing_address()) {
            if (isset($_POST[self::BILLING_HOUSE_NUMBER_KEY])) {
                $houseNumber = sanitize_text_field($_POST[self::BILLING_HOUSE_NUMBER_KEY]);
                $order->update_meta_data(self::BILLING_HOUSE_NUMBER_KEY, $houseNumber);
            }
        }

        if ($order->has_shipping_address()) {
            if (isset($_POST[self::SHIPPING_HOUSE_NUMBER_KEY])) {
                $houseNumber = sanitize_text_field($_POST[self::SHIPPING_HOUSE_NUMBER_KEY]);
                $order->update_meta_data(self::SHIPPING_HOUSE_NUMBER_KEY, $houseNumber);
            }
        }
    }

    private function addFields(array $fields): array
    {
        $updatedFields = $this->insertFieldsAtPosition($fields, [
            self::HOUSE_NUMBER_FIELD => [
                'placeholder' => __('House number', I18N::DOMAIN),
                'required' => false,
                'type' => 'text',
                'priority' => 50,
                'hidden' => true,
                'class' => ['form-row-last', 'address-field'],
                'label' => __('House number', I18N::DOMAIN),
            ],
        ],
            self::STREET_ADDRESS_POSITION
        );

        return $updatedFields;
    }

    private function updateFields(array $fields): array
    {
        $fields['address_1']['custom_attributes'] = ['readonly' => 'readonly'];
        $fields['city']['custom_attributes'] = ['readonly' => 'readonly'];
        $fields['postcode']['class'] = ['form-row-first', 'address-field'];

        return $fields;
    }

    private function insertFieldsAtPosition(array $originalFields, array $newFields, int $position): array
    {
        $updatedFields = array_slice($originalFields, 0, $position, true);
        $updatedFields += $newFields;

        $updatedFields += array_slice($originalFields, $position, count($originalFields) - 1, true);

        return $updatedFields;
    }
}
