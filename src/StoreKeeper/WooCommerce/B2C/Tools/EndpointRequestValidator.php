<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Exceptions\WpRestException;
use StoreKeeper\WooCommerce\B2C\Models\WebhookLogModel;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;

class EndpointRequestValidator
{
    /**
     * @var WordpressRestRequestWrapper ->request
     */
    private $wrappedRequest;

    /**
     * @return WordpressRestRequestWrapper
     */
    public function getWrappedRequest()
    {
        return $this->wrappedRequest;
    }

    public function setWrappedRequest(WordpressRestRequestWrapper $wrappedRequest)
    {
        $this->wrappedRequest = $wrappedRequest;
    }

    /**
     * RequestValidator constructor.
     */
    public function __construct(WordpressRestRequestWrapper $request)
    {
        $this->setWrappedRequest($request);
    }

    public function tokenCheck()
    {
        $requestToken = $this->wrappedRequest->getRequest()->get_header('UpxHookToken');
        $shopToken = WooCommerceOptions::get(WooCommerceOptions::WOOCOMMERCE_TOKEN);

        return $requestToken === $shopToken;
    }

    protected function bodyCheck()
    {
        $action = $this->wrappedRequest->getBodyParam('action');
        $payload = $this->wrappedRequest->getBodyParam('payload');

        return isset($action) && isset($payload);
    }

    protected function saveRequest($httpCode)
    {
        $request = $this->wrappedRequest->getRequest();
        $body = json_decode($request->get_body(), true);
        WebhookLogModel::create(
            [
                'action' => $body['action'],
                'body' => $request->get_body(),
                'headers' => json_encode($request->get_headers()),
                'method' => $request->get_method(),
                'response_code' => $httpCode,
                'route' => $request->get_route(),
            ]
        );
    }

    public function checkValid()
    {
        $code = 200;

        if (!isset($this->wrappedRequest)) {
            $code = 500;
        }

        if (!$this->tokenCheck()) {
            $code = 401;
        }

        if (!$this->bodyCheck()) {
            $code = 400;
        }

        $this->saveRequest($code);

        if (200 !== $code) {
            throw new WpRestException('Request is not valid', $code);
        }
    }
}
