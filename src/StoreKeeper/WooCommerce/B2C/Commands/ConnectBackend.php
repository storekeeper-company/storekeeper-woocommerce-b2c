<?php


namespace StoreKeeper\WooCommerce\B2C\Commands;


use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;

class ConnectBackend extends AbstractCommand
{
    public static function getCommandName(): string
    {
        return 'connect-backend';
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        $rootUrl = $arguments[0] ?? site_url();
        $key = WooCommerceOptions::getApiKey($rootUrl);

        echo "Site url: $rootUrl\n";
        echo "Use this key to connect to wordpress:\n$key\n";
    }

}
