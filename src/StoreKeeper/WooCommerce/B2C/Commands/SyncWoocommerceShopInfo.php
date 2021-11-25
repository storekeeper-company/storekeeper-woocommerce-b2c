<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Helpers\WpCliHelper;
use StoreKeeper\WooCommerce\B2C\I18N;

class SyncWoocommerceShopInfo extends AbstractSyncCommand
{
    /**
     * Execute this command to fully sync your backend products to WooCommerce.
     *
     * @throws \Exception
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            $shopData = $this->getShopRelationData();

            if ($shopData->has('relation_data.contact_address.flatnumber')) {
                $addressContact = $shopData->get('relation_data.contact_address.street').' '.$shopData->get(
                        'relation_data.contact_address.streetnumber'
                    ).' '.$shopData->get('relation_data.contact_address.flatnumber');
            } else {
                $addressContact = $shopData->get('relation_data.contact_address.street').' '.$shopData->get(
                        'relation_data.contact_address.streetnumber'
                    );
            }

            $city = $shopData->get('relation_data.contact_address.city');
            $postal = $shopData->get('relation_data.contact_address.zipcode');
            $country_iso2 = strtoupper($shopData->get('relation_data.contact_address.country_iso2'));
            $currency_iso3 = strtoupper($this->getCurrencyIso3());

            $email_name = $shopData->get('relation_data.business_data.name');
            $email = $shopData->get('relation_data.contact_set.email');

            // address
            update_option('woocommerce_store_address', $addressContact);
            update_option('woocommerce_store_city', $city);
            update_option('woocommerce_store_postcode', $postal);
            update_option('woocommerce_default_country', "$country_iso2:*");
            update_option('woocommerce_currency', $currency_iso3);

            // Email
            update_option('woocommerce_email_from_name', $email_name);
            update_option('woocommerce_email_from_address', $email);
            update_option('sendgrid_from_name', $email_name);
            update_option('sendgrid_from_email', $email);

            WpCliHelper::attemptSuccessOutput(__('Done synchronizing shop information', I18N::DOMAIN));
        }
    }

    /**
     * @return string
     *
     * @throws \Exception
     */
    private function getCurrencyIso3()
    {
        $response = $this->api->getModule('ShopModule')->listConfigurations(
            0,
            1,
            null,
            [
                [
                    'name' => 'is_default__=',
                    'val' => '1',
                ],
            ]
        );
        $data = $response['data'];
        if (empty($data)) {
            return 'EUR'; // Fallback
        }

        return $data[0]['currency_iso3'];
    }

    /**
     * @return Dot
     */
    private function getShopRelationData()
    {
        $data = $this->api->getModule('ShopModule')->getShopWithRelation();

        return new Dot($data);
    }
}
