<?php

namespace StoreKeeper\WooCommerce\B2C\PaymentGateway;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

final class BlockSupport extends AbstractPaymentMethodType
{
    private StoreKeeperBaseGateway $gateway;
    public function __construct(array $method)
    {
        $this->name = "sk_pay_id_{$method['id']}";
    }
    public function initialize()
    {
        $gateways       = WC()->payment_gateways->payment_gateways();
        $this->gateway  = $gateways[ $this->name ];
    }
    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {
        $script_path       = '/assets/js/frontend/blocks.js';
        $script_asset_path =  Core::plugin_abspath(). 'assets/js/frontend/blocks.asset.php';
        $script_asset      = file_exists( $script_asset_path )
            ? require( $script_asset_path )
            : array(
                'dependencies' => array(),
                'version'      => '1.2.0'
            );
        $script_url        = Core::plugin_url() . $script_path;

        wp_register_script(
            'wc-storekeeper-payments-blocks',
            $script_url,
            $script_asset[ 'dependencies' ],
            $script_asset[ 'version' ],
            true
        );

        if ( function_exists( 'wp_set_script_translations' ) ) {
            wp_set_script_translations( 'wc-storekeeper-payments-blocks', 'woocommerce-gateway-dummy', Core::plugin_abspath() . 'languages/' );
        }

        return [ 'wc-storekeeper-payments-blocks' ];
    }
    public function get_payment_method_data() {
        return [
            'title'               => $this->gateway->title,
            'supports'    => array_filter( $this->gateway->supports, [ $this->gateway, 'supports' ] ),
            // todo icon
        ];
    }
}
