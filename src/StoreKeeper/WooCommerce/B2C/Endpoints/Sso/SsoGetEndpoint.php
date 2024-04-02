<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\Sso;

use StoreKeeper\WooCommerce\B2C\Endpoints\AbstractEndpoint;
use StoreKeeper\WooCommerce\B2C\Exceptions\SsoAuthException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpRestException;
use StoreKeeper\WooCommerce\B2C\Helpers\SsoHelper;

class SsoGetEndpoint extends AbstractEndpoint
{
    public const ROUTE = 'sso';

    public function handle()
    {
        $transient_key = $this->wrappedRequest->getRequest()->get_param('key');
        try {
            $shortname = SsoHelper::verifySso($transient_key);
            $this->logger->info("User with shortname '$shortname' tries to SSO");

            SsoHelper::loginUser($shortname);

            SsoHelper::redirectUser();
        } catch (SsoAuthException $exception) {
            throw new WpRestException($exception->getMessage(), 401, $exception);
        } catch (\Throwable $exception) {
            throw new WpRestException('Something went wrong', 500, $exception);
        }
    }
}
