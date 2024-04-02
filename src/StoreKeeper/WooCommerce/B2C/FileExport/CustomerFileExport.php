<?php

namespace StoreKeeper\WooCommerce\B2C\FileExport;

use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\Language;

class CustomerFileExport extends AbstractCSVFileExport
{
    public const FALLBACK_COUNTRY_ISO2 = 'NL';

    public function getType(): string
    {
        return FileExportTypeHelper::CUSTOMER;
    }

    public function getPaths(): array
    {
        return [
            'id' => 'Relation number',
            'shortname' => 'GUID',

            'language_iso2' => 'Language (iso2)',

            'business_data.name' => 'Company',
            'business_data.coc_number' => 'Company number',
            'business_data.country_iso2' => 'Company country',
            'business_data.vat_number' => 'Company vat',

            'contact_person.firstname' => 'First name',
            'contact_person.familyname_prefix' => 'Family name prefix',
            'contact_person.familyname' => 'Family name',

            'contact_set.email' => 'Email',
            'contact_set.phone' => 'Phone',
            'contact_set.fax' => 'Fax',
            'contact_set.www' => 'Website',

            'contact_set.allow_general_communication' => 'Communication: general',
            'contact_set.allow_offer_communication' => 'Communication: sales',
            'contact_set.allow_special_communication' => 'Communication: special',

            'contact_address.name' => 'Address name',
            'contact_address.state' => 'State',
            'contact_address.city' => 'City',
            'contact_address.zipcode' => 'Zipcode',
            'contact_address.street' => 'Street',
            'contact_address.streetnumber' => 'Street number',
            'contact_address.flatnumber' => 'Flat number',
            'contact_address.country_iso2' => 'Country iso2',

            'address_billing.name' => 'Address name',
            'address_billing.state' => 'State',
            'address_billing.city' => 'City',
            'address_billing.zipcode' => 'Zipcode',
            'address_billing.street' => 'Street',
            'address_billing.streetnumber' => 'Street number',
            'address_billing.flatnumber' => 'Flat number',
            'address_billing.country_iso2' => 'Country iso2',
        ];
    }

    public function runExport(?string $exportLanguage = null): string
    {
        $exportLanguage = $exportLanguage ?? Language::getSiteLanguageIso2();
        $arguments = [
            'role' => 'customer',
        ];
        $users = get_users($arguments);
        $total = count($users);
        foreach ($users as $index => $user) {
            $customer = new \WC_Customer($user->ID);

            $lineData = [];
            $lineData = $this->exportGenericInfo($lineData, $customer, $exportLanguage);
            $lineData = $this->exportBusinessInfo($lineData, $customer);
            $lineData = $this->exportContactInfo($lineData, $customer, $user);
            $lineData = $this->exportCommunicationInfo($lineData);
            $lineData = $this->exportInvoiceAddress($lineData, $customer);
            $lineData = $this->exportDeliveryAddress($lineData, $customer);

            $this->writeLineData($lineData);

            if (0 === $index % 25) {
                $this->reportUpdate($total, $index, 'Exported 25 customers');
            }
        }

        return $this->filePath;
    }

    private function exportGenericInfo(array $lineData, \WC_Customer $customer, string $overwriteLanguage): array
    {
        $lineData['shortname'] = $this->getShortname($customer);
        $lineData['language_iso2'] = $overwriteLanguage;

        return $lineData;
    }

    private function getShortname(\WC_Customer $customer): string
    {
        $username = $customer->get_username();
        $id = $customer->get_id();
        $shortname = strtolower("wp-$username-$id");

        return sanitize_title($shortname);
    }

    private function exportBusinessInfo(array $lineData, \WC_Customer $customer): array
    {
        if (!empty($customer->get_billing_company())) {
            $lineData['business_data.name'] = $customer->get_billing_company();
            $lineData['business_data.country_iso2'] = $customer->get_billing_country() ?? self::FALLBACK_COUNTRY_ISO2;
        }

        return $lineData;
    }

    private function exportContactInfo(array $lineData, \WC_Customer $customer, \WP_User $user): array
    {
        $lineData['contact_person.firstname'] = $customer->get_first_name();
        $lineData['contact_person.familyname'] = $customer->get_last_name();
        $lineData['contact_set.email'] = $customer->get_email();
        $lineData['contact_set.phone'] = $customer->get_billing_phone();
        $lineData['contact_set.www'] = $user->user_url;

        return $lineData;
    }

    private function exportCommunicationInfo(array $lineData): array
    {
        $lineData['contact_set.allow_general_communication'] = true;
        $lineData['contact_set.allow_offer_communication'] = true;
        $lineData['contact_set.allow_special_communication'] = true;

        return $lineData;
    }

    private function exportInvoiceAddress(array $lineData, \WC_Customer $customer): array
    {
        $lineData['address_billing.name'] = self::getFormattedBillingFullName($customer);
        $lineData['address_billing.state'] = $customer->get_billing_state();
        $lineData['address_billing.city'] = $customer->get_billing_city();
        $lineData['address_billing.zipcode'] = $customer->get_billing_postcode();
        $lineData['address_billing.street'] = $this->getFormattedBillingStreet($customer);
        $lineData['address_billing.country_iso2'] = $customer->get_billing_country() ?? self::FALLBACK_COUNTRY_ISO2;

        return $lineData;
    }

    private function exportDeliveryAddress(array $lineData, \WC_Customer $customer): array
    {
        $lineData['contact_address.name'] = self::getFormattedShippingFullName($customer);
        $lineData['contact_address.state'] = $customer->get_shipping_state();
        $lineData['contact_address.city'] = $customer->get_shipping_city();
        $lineData['contact_address.zipcode'] = $customer->get_shipping_postcode();
        $lineData['contact_address.street'] = $this->getFormattedShippingStreet($customer);
        $lineData['contact_address.country_iso2'] = $customer->get_shipping_country() ?? self::FALLBACK_COUNTRY_ISO2;

        return $lineData;
    }

    public static function getFormattedBillingStreet(\WC_Customer $customer): string
    {
        return self::getFormattedStreet(
            $customer->get_billing_address_1(),
            $customer->get_billing_address_2()
        );
    }

    public static function getFormattedShippingStreet(\WC_Customer $customer): string
    {
        return self::getFormattedStreet(
            $customer->get_shipping_address_1(),
            $customer->get_shipping_address_2()
        );
    }

    public static function getFormattedStreet(string $first = '', string $second = ''): string
    {
        return trim($first).' '.trim($second);
    }

    public static function getFormattedBillingFullName(\WC_Customer $customer): string
    {
        return self::getFormattedFullName(
            $customer->get_billing_first_name(),
            $customer->get_billing_last_name()
        );
    }

    public static function getFormattedShippingFullName(\WC_Customer $customer): string
    {
        return self::getFormattedFullName(
            $customer->get_shipping_first_name(),
            $customer->get_shipping_last_name()
        );
    }

    public static function getFormattedFullName(string $firstname = '', string $lastname = ''): string
    {
        // Translation take from WooCommerce
        return sprintf(
            _x('%1$s %2$s', 'full name', I18N::DOMAIN),
            $firstname,
            $lastname
        );
    }
}
