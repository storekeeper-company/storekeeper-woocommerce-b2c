<?php

namespace StoreKeeper\WooCommerce\B2C;

/**
 * Class    I18n.
 *
 * @since   0.0.1
 */
class I18N
{
    const DOMAIN = 'storekeeper-woocommerce-b2c';

    /**
     * Load the plugin text domain for translation.
     *
     * @since    0.0.1
     */
    public function load_plugin_textdomain()
    {
        load_plugin_textdomain(
            self::DOMAIN,
            false,
            'storekeeper-woocommerce-b2c/i18n/'
        );
    }
}
