<?php

namespace StoreKeeper\WooCommerce\B2C\PaymentGateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use StoreKeeper\WooCommerce\B2C\Core;

final class BlockSupport extends AbstractPaymentMethodType
{
    /**
     * The gateway instance.
     */
    private StoreKeeperBaseGateway $gateway;
    private array $method = [];

    public function __construct(array $method)
    {
        $this->method = $method;
        $this->name = PaymentGateway::STOREKEEPER_PAYMENT_ID_PREFIX.$this->method['id'];
    }

    /**
     * Initializes the payment method type.
     */
    public function initialize()
    {
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = $gateways[$this->name];
    }

    /**
     * Returns if this payment method should be active. If false, the scripts will not be enqueued.
     *
     * @return bool
     */
    public function is_active()
    {
        return $this->gateway->is_available();
    }

    /**
     * Returns an array of scripts/handles to be registered for this payment method.
     */
    public function get_payment_method_script_handles(): array
    {
        // We register same script for all dynamic storekeeper payment methods
        // and let the script handle the loop using @woocommerce/settings
        $script_path = '/assets/js/frontend/blocks.js';
        $script_asset_path = Core::plugin_abspath().'assets/js/frontend/blocks.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require ($script_asset_path)
            : [
                'dependencies' => [],
                'version' => '1.2.0',
            ];
        $script_url = Core::plugin_url().$script_path;

        wp_register_script(
            'wc-storekeeper-payments-blocks',
            $script_url,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('wc-storekeeper-payments-blocks', 'storekeeper-for-woocommerce', Core::plugin_abspath().'i18n/');
        }

        return ['wc-storekeeper-payments-blocks'];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => $this->gateway->title,
            'supports' => array_filter($this->gateway->supports, [$this->gateway, 'supports']),
            'icon' => $this->gateway->icon,
            'storekeeper_id' => $this->method['id'],
        ];
    }
}
