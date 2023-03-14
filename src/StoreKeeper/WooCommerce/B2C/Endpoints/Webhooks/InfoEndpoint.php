<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks;

use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Endpoints\AbstractEndpoint;
use StoreKeeper\WooCommerce\B2C\Exceptions\AccessDeniedException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpRestException;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;

class InfoEndpoint extends AbstractEndpoint
{
    public const ROUTE = 'info';

    public const AUTH_TYPE = 'header-token';

    /**
     * @throws WpRestException
     */
    public function handle()
    {
        $apiToken = $this->wrappedRequest->getRequest()->get_param('api-token');

        $response = [];

        try {
            if ($apiToken !== WooCommerceOptions::get(WooCommerceOptions::WOOCOMMERCE_INFO_TOKEN)) {
                throw new AccessDeniedException('Token is not valid');
            }

            $response['account_name'] = $this->getAccountName();
            $response['shop'] = $this->getShopDetails();
            $response['webhook'] = $this->getWebhookDetails();
        } catch (AccessDeniedException $exception) {
            throw new WpRestException('Access Denied: '.$exception->getMessage(), 401, $exception);
        } catch (GeneralException $exception) {
            throw new WpRestException('General exception: '.$exception->getMessage(), 500, $exception);
        } catch (\Throwable $exception) {
            throw new WpRestException('Something went wrong', 500, $exception);
        }

        return $response;
    }

    private function getAccountName(): ?string
    {
        $syncAuth = StoreKeeperOptions::get(StoreKeeperOptions::SYNC_AUTH);

        if (!is_null($syncAuth)) {
            $accountName = isset($syncAuth['account']) ? $syncAuth['account'] : null;

            return $accountName;
        }

        return null;
    }

    private function getShopDetails(): array
    {
        $shopDetails = InfoHandler::gatherInformation();
        unset($shopDetails['extra']);
        $shopDetails['name'] = get_bloginfo('name');
        $shopDetails['url'] = get_bloginfo('url');
        $shopDetails['alias'] = WooCommerceOptions::get(WooCommerceOptions::WOOCOMMERCE_UUID);

        return $shopDetails;
    }

    private function getWebhookDetails(): array
    {
        return [
            'url' => WooCommerceOptions::getWebhookUrl(),
            'auth' => self::AUTH_TYPE,
            'webhook_secret' => WooCommerceOptions::get(WooCommerceOptions::WOOCOMMERCE_TOKEN),
        ];
    }
}
