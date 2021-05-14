<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

class ConfigurePaynl extends AbstractCommand
{
    public static function needsFullWpToExecute(): bool
    {
        return true;
    }

    private $availableLogoSizes = [
        '0',
        '50x32',
        '40x26',
        '20x20',
        '25x25',
        '50x50',
        '75x75',
        '100x100',
    ];

    private $availablePaymentLangauges = [
        'nl',
        'en',
        'de',
        'es',
        'it',
        'fr',
        'browser',
    ];

    private $testModuleTruelyValues = [
        'true',
        'yes',
        '1',
    ];

    private $testModuleFalselyValues = [
        'false',
        'no',
        '0',
    ];

    /**
     * To configure the payNL settings.
     *
     * [--token_code=<token_code>]
     * : The Token Code
     *
     * [--api_token=<api_token>]
     * : The Api Token
     *
     * [--service_id=<service_id>]
     * : The Service ID
     *
     * [--test_module=<test_module>]
     * : Should be true or false, yes or no is also good.
     *
     * [--payment_screen_language=<payment_screen_language>]
     * : Should to one of the following option:
     *      - nl
     *      - en
     *      - de
     *      - es
     *      - it
     *      - fr
     *      - browser - uses the browsers language
     *
     * [--logo_size=<logo_size>]
     * : Changes the size of the payment methods, choose of the the following options:
     *      - 0 - No logo
     *      - 50x32
     *      - 40x26
     *      - 20x20
     *      - 25x25 - default
     *      - 50x50
     *      - 75x75
     *      - 100x100
     *
     * @when before_wp_load
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        // Values without checks
        $this->setAssocArguments($assoc_arguments, 'token_code', 'paynl_tokencode');
        $this->setAssocArguments($assoc_arguments, 'api_token', 'paynl_apitoken');
        $this->setAssocArguments($assoc_arguments, 'service_id', 'paynl_serviceid');

        if (array_key_exists('test_module', $assoc_arguments)) {
            $value = $assoc_arguments['test_module'];
            if (in_array($value, $this->testModuleTruelyValues)) {
                update_option('paynl_test_mode', 'yes');
            } else {
                if (in_array($value, $this->testModuleFalselyValues)) {
                    update_option('paynl_test_mode', 'no');
                }
            }
        }

        // Values with checks
        $this->setAssocArguments(
            $assoc_arguments,
            'payment_screen_language',
            'paynl_language',
            $this->availablePaymentLangauges
        );
        $this->setAssocArguments($assoc_arguments, 'logo_size', 'paynl_logo_size', $this->availableLogoSizes);
    }

    private function setAssocArguments($assoc_arguments, $assoc_key, $option_key, array $valid_options = null)
    {
        if (array_key_exists($assoc_key, $assoc_arguments)) {
            $value = $assoc_arguments[$assoc_key];
            if (is_array($valid_options)) {
                if (in_array($value, $valid_options)) {
                    update_option($option_key, $value);
                }
            } else {
                update_option($option_key, $value);
            }
        }
    }
}
