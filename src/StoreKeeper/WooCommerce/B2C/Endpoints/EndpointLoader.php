<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints;

use StoreKeeper\WooCommerce\B2C\Backoffice\Helpers\ProductXEditor;
use StoreKeeper\WooCommerce\B2C\Endpoints\FileExport\ExportEndpoint;
use StoreKeeper\WooCommerce\B2C\Endpoints\Sso\SsoGetEndpoint;
use StoreKeeper\WooCommerce\B2C\Endpoints\TaskProcessor\TaskProcessorEndpoint;
use StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks\InfoEndpoint;
use StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks\WebhookPostEndpoint;
use StoreKeeper\WooCommerce\B2C\Endpoints\WebService\AddressSearchEndpoint;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Objects\PluginStatus;

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
                'methods' => 'GET, POST',
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

        $addressSearch = new AddressSearchEndpoint();
        register_rest_route(
            $namespace,
            AddressSearchEndpoint::ROUTE,
            [
                'methods' => 'GET',
                'callback' => [$addressSearch, 'handleRequest'],
                'permission_callback' => '__return_true',
            ]
        );

        $restInfo = new InfoEndpoint();
        register_rest_route(
            $namespace,
            InfoEndpoint::ROUTE,
            [
                'methods' => 'GET',
                'callback' => [$restInfo, 'handleRequest'],
                'permission_callback' => '__return_true',
            ]
        );

        if (PluginStatus::isProductXEnabled()) {
            try {
                $productX = new ProductXEditor();
                $productX->registerRoutes();
            } catch (\Throwable $e) {
                LoggerFactory::create('load_errors')->error(
                    'Woocommerce Builder api cannot be loaded:  '.$e->getMessage(),
                    ['trace' => $e->getTraceAsString()]
                );
            }
        }
    }
}
