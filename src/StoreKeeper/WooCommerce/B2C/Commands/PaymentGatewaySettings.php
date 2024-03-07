<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Exceptions\NotConnectedException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WpCliException;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

class PaymentGatewaySettings extends AbstractCommand
{
    /**
     * This command allows you to change settings regarding the Payment Gateway.
     *
     * [--active]
     * You can set if the Payment Gateway should be active or not. Backend should be connected when activating it.
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        if (!empty($assoc_arguments['active'])) {
            if ('true' === $assoc_arguments['active'] || 1 == $assoc_arguments['active']) {
                if (!StoreKeeperOptions::isConnected()) {
                    new NotConnectedException();
                }
                // activate
                StoreKeeperOptions::set(StoreKeeperOptions::PAYMENT_GATEWAY_ACTIVATED, 'yes');
            } else {
                // deactivate
                StoreKeeperOptions::set(StoreKeeperOptions::PAYMENT_GATEWAY_ACTIVATED, 'no');
            }
        } else {
            new WpCliException('Please provide --active argument (true/false)');
        }
    }
}
