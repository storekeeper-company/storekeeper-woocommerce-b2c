<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks;

use StoreKeeper\WooCommerce\B2C\Exceptions\SsoAuthException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpRestException;
use StoreKeeper\WooCommerce\B2C\Helpers\SsoHelper;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressRestRequestWrapper;

class SsoHandler
{
    /**
     * @var WordpressRestRequestWrapper
     */
    private $wrappedRequest;

    /**
     * InitHandler constructor.
     */
    public function __construct(WordpressRestRequestWrapper $request)
    {
        $this->wrappedRequest = $request;
    }

    /**
     * this function handles the creation of sso link which user can use to login.
     *
     * @throws WpRestException
     */
    public function run(): array
    {
        $fields = ['name', 'shortname', 'role'];
        foreach ($fields as $field) {
            $data = $this->wrappedRequest->getPayloadParam($field);
            if (!$data) {
                throw new WpRestException("Missing or empty field \"$field\"", 400);
            }
        }

        $name = $this->wrappedRequest->getPayloadParam('name');
        $shortname = $this->wrappedRequest->getPayloadParam('shortname');
        $role = $this->wrappedRequest->getPayloadParam('role');
        $expiration = $this->wrappedRequest->getPayloadParam('expiration');

        try {
            if (!SsoHelper::existsUser($shortname)) {
                SsoHelper::createUser($name, $shortname, $role);
            } else {
                SsoHelper::updateUser($name, $shortname, $role);
            }

            $transient_key = SsoHelper::createSso($shortname, $expiration);

            return [
                'url' => SsoHelper::createSsoLink($transient_key),
            ];
        } catch (SsoAuthException $exception) {
            throw new WpRestException($exception->getMessage(), 401, $exception);
        }
    }
}
