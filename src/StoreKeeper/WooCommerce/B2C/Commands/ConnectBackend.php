<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;

class ConnectBackend extends AbstractCommand
{
    public static function getCommandName(): string
    {
        return 'connect-backend';
    }

    public static function getShortDescription(): string
    {
        return __('Generate Storekeeper Backoffice API key.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        $examples = static::generateExamples([
            'wp sk connect-backend https://your-wordpress-site.com',
        ]);

        return __('Execute this command to generate an API key that will be used to connect synchronization on Storekeeper Backoffice.', I18N::DOMAIN).$examples;
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'positional',
                'name' => 'url',
                'description' => __('The base URL of your website.', I18N::DOMAIN),
                'optional' => false,
                'default' => site_url(),
            ],
        ];
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        $rootUrl = $arguments[0] ?? site_url();
        $key = WooCommerceOptions::getApiKey($rootUrl);

        $rootUrl = esc_url($rootUrl);
        echo "Site url: $rootUrl\n";
        echo "Use this key to connect to wordpress:\n$key\n";
    }
}
