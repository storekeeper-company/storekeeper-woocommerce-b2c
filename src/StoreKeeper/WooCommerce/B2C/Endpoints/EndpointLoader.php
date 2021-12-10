<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints;

use StoreKeeper\WooCommerce\B2C\Endpoints\FileExport\ExportEndpoint;
use StoreKeeper\WooCommerce\B2C\Endpoints\Sso\SsoGetEndpoint;
use StoreKeeper\WooCommerce\B2C\Endpoints\TaskProcessor\TaskProcessorEndpoint;
use StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks\WebhookPostEndpoint;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;

class EndpointLoader
{
    private const NAMESPACE = 'storekeeper-woocommerce-b2c';
    private const VERSION = 'v1';

    public static function getFullNamespace(): string
    {
        return self::NAMESPACE.'/'.self::VERSION;
    }

    public function load(): void
    {
        $namespace = static::getFullNamespace();

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
                'callback' => [$taskProcessor, 'handleRequestSilently'],
                'permission_callback' => '__return_true',
            ]
        );

        $export = new ExportEndpoint();
        $export->setLogger($logger);
        register_rest_route(
            $namespace,
            ExportEndpoint::ROUTE,
            [
                'methods' => 'GET',
                'callback' => [$export, 'handleRequest'],
                'permission_callback' => '__return_true',
            ]
        );
    }
}
