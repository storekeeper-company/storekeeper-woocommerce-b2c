<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints;

use StoreKeeper\WooCommerce\B2C\Endpoints\Sso\SsoGetEndpoint;
use StoreKeeper\WooCommerce\B2C\Endpoints\TaskProcessor\TaskProcessorEndpoint;
use StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks\WebhookPostEndpoint;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;

class EndpointLoader
{
    private function getNamespace()
    {
        return 'storekeeper-woocommerce-b2c';
    }

    protected function getVersion()
    {
        return 'v1';
    }

    public function load()
    {
        $namespace = $this->getNamespace().'/'.$this->getVersion();

        $logger = LoggerFactory::create('endpoint');
        $webhook = new WebhookPostEndpoint();
        $webhook->setLogger($logger);
        register_rest_route(
            $namespace,
            WebhookPostEndpoint::ROUTE,
            [
                'methods' => 'POST',
                'callback' => [$webhook, 'handleRequest'],
                'permission_callback' => '__return_true',
            ]
        );

        $webhook = new SsoGetEndpoint();
        $webhook->setLogger($logger);
        register_rest_route(
            $namespace,
            SsoGetEndpoint::ROUTE,
            [
                'methods' => 'GET',
                'callback' => [$webhook, 'handleRequest'],
                'permission_callback' => '__return_true',
            ]
        );

        $taskProcessor = new TaskProcessorEndpoint();
        $taskProcessor->setLogger($logger);
        register_rest_route(
            $namespace,
            TaskProcessorEndpoint::ROUTE,
            [
                'methods' => 'GET',
                'callback' => [$taskProcessor, 'handleRequest'],
                'permission_callback' => '__return_true',
            ]
        );
    }
}
