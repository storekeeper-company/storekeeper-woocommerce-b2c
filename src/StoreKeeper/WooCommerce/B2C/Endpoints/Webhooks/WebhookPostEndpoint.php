<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks;

use StoreKeeper\WooCommerce\B2C\Endpoints\AbstractEndpoint;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpRestException;
use StoreKeeper\WooCommerce\B2C\Tools\EndpointRequestValidator;

class WebhookPostEndpoint extends AbstractEndpoint
{
    public const ROUTE = 'webhook';

    public function handle()
    {
        $validator = new EndpointRequestValidator($this->wrappedRequest);
        $action = $this->wrappedRequest->getAction();
        $validator->checkValid();

        switch ($action) {
            case 'init':
                $handler = new InitHandler($this->wrappedRequest);

                return $handler->run();
            case 'events':
                $handler = new EventsHandler($this->wrappedRequest);
                $handler->setLogger($this->logger);

                return $handler->run();
            case 'sso':
                $handler = new SsoHandler($this->wrappedRequest);

                return $handler->run();
            case 'info':
                $handler = new InfoHandler();

                return $handler->run();
            case 'disconnect':
                $handler = new DisconnectHandler();

                return $handler->run();
        }
        throw new WpRestException("Unknown action: $action", 500);
    }
}
