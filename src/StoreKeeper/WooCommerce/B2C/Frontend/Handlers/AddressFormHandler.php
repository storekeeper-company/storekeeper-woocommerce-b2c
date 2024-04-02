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

    /**
     * Retrieves value of custom fields from session to be displayed as default value on form.
     *
     * @hook $this->loader->add_filter('woocommerce_billing_fields', $addressFormHandler, 'setHouseNumberValueFromSession', 11);
     * @hook $this->loader->add_filter('woocommerce_shipping_fields', $addressFormHandler, 'setHouseNumberValueFromSession', 11);
     */
    public function setHouseNumberValueFromSession(array $addressFields): array
    {
        $billingHouseNumberKey = self::BILLING_HOUSE_NUMBER_KEY;
        $shippingHouseNumberKey = self::SHIPPING_HOUSE_NUMBER_KEY;
        $billingHouseNumberSession = WC()->session->get($billingHouseNumberKey);
        $shippingHouseNumberSession = WC()->session->get($shippingHouseNumberKey);
        if (isset($addressFields[$billingHouseNumberKey])) {
            $addressFields[self::BILLING_HOUSE_NUMBER_KEY]['default'] = $billingHouseNumberSession ?? null;
        }

        if (isset($addressFields[$shippingHouseNumberKey])) {
            $addressFields[self::SHIPPING_HOUSE_NUMBER_KEY]['default'] = $shippingHouseNumberSession;
        }

        return $addressFields;
    }

    /**
     * Save house number to order metadata be retrieved for displaying.
     */
    public function saveCustomFields(\WC_Order $order): void
    {
        $billingHouseNumberKey = self::BILLING_HOUSE_NUMBER_KEY;
        $shippingHouseNumberKey = self::SHIPPING_HOUSE_NUMBER_KEY;
        $billingHouseNumber = sanitize_text_field($_POST[$billingHouseNumberKey]);
        $shippingHouseNumber = sanitize_text_field($_POST[$shippingHouseNumberKey]);
        if ($billingHouseNumber && $order->has_billing_address()) {
            $order->update_meta_data($billingHouseNumberKey, $billingHouseNumber);
        }
        if ($order->has_shipping_address()) {
            if (isset($shippingHouseNumber) & !empty($shippingHouseNumber)) {
                $order->update_meta_data($shippingHouseNumberKey, $shippingHouseNumber);
            } else {
                $order->update_meta_data($shippingHouseNumberKey, $billingHouseNumber);
            }
        }
    }

    /**
     * Saves the custom fields to the session for it to be kept and used during form rendering.
     *
     * @hook $this->loader->add_action('woocommerce_checkout_process', $addressFormHandler, 'saveCustomFieldsToSession');
     */
    public function saveCustomFieldsToSession(): void
    {
        $billingHouseNumberKey = self::BILLING_HOUSE_NUMBER_KEY;
        $shippingHouseNumberKey = self::SHIPPING_HOUSE_NUMBER_KEY;

        if (isset($_POST[$billingHouseNumberKey])) {
            WC()->session->set($billingHouseNumberKey, sanitize_text_field($_POST[$billingHouseNumberKey]));
        }

        if (isset($_POST['ship_to_different_address']) && '1' === $_POST['ship_to_different_address']) {
            WC()->session->set($shippingHouseNumberKey, sanitize_text_field($_POST[$shippingHouseNumberKey]));
        } elseif (!isset($_POST['ship_to_different_address'])) {
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
                isset($_POST[self::SHIPPING_HOUSE_NUMBER_KEY], $_POST[self::BILLING_HOUSE_NUMBER_KEY])
                && $isShippingSubmitted
                && empty(sanitize_text_field($_POST[self::SHIPPING_HOUSE_NUMBER_KEY]))
                && !empty(sanitize_text_field($_POST[self::BILLING_HOUSE_NUMBER_KEY]))
            ) {
                WC()->session->set($shippingHouseNumberKey, sanitize_text_field($_POST[$billingHouseNumberKey]));
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
