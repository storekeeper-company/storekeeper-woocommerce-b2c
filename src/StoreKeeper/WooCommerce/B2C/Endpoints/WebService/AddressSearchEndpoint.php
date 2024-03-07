<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\WebService;

use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Endpoints\AbstractEndpoint;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpRestException;
use StoreKeeper\WooCommerce\B2C\Exports\OrderExport;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

class AddressSearchEndpoint extends AbstractEndpoint
{
    public const ROUTE = 'address-search';
    public const DEFAULT_COUNTRY_ISO = 'NL';

    /**
     * @throws WpRestException
     */
    public function handle()
    {
        $postCode = $this->wrappedRequest->getRequest()->get_param('postCode');
        $houseNumber = $this->wrappedRequest->getRequest()->get_param('houseNumber');

        if (empty($postCode) || empty($houseNumber)) {
            throw new WpRestException(__('Postcode and house number parameter is required.'), 400);
        }

        $splitStreet = OrderExport::splitStreetNumber($houseNumber);
        $streetNumber = $splitStreet['streetnumber'];

        try {
            $response = self::validateAddress($postCode, $streetNumber);
        } catch (GeneralException $exception) {
            throw new WpRestException('General exception  - '.$exception->getMessage(), 500, $exception);
        } catch (\Throwable $exception) {
            throw new WpRestException('Something went wrong', 500, $exception);
        }

        return $response;
    }

    public static function validateAddress($postCode, $houseNumber)
    {
        $api = StoreKeeperApi::getApiByAuthName();
        $module = $api->getModule('RelationsModule');

        // Only works with NL country
        return $module->searchAddressByZipcode($postCode, $houseNumber, self::DEFAULT_COUNTRY_ISO);
    }
}
