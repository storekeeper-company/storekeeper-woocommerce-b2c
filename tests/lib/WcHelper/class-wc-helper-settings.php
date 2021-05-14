<?php

/**
 * Class WC_Helper_Settings.
 *
 * This helper class should ONLY be used for unit tests!
 */
class WC_Helper_Settings
{
    /**
     * Hooks in some dummy data for testing the settings REST API.
     *
     * @since 3.0.0
     */
    public static function register()
    {
        add_filter('woocommerce_settings_groups', ['WC_Helper_Settings', 'register_groups']);
        add_filter('woocommerce_settings-test', ['WC_Helper_Settings', 'register_test_settings']);
    }

    /**
     * Registers some example setting groups, including invalid ones that should not show up in JSON responses.
     *
     * @param array $groups
     *
     * @return array
     *
     * @since 3.0.0
     */
    public static function register_groups($groups)
    {
        $groups[] = [
            'id' => 'test',
            'bad' => 'value',
            'label' => 'Test extension',
            'description' => 'My awesome test settings.',
            'option_key' => '',
        ];
        $groups[] = [
            'id' => 'sub-test',
            'parent_id' => 'test',
            'label' => 'Sub test',
            'description' => '',
            'option_key' => '',
        ];
        $groups[] = [
            'id' => 'coupon-data',
            'label' => 'Coupon data',
            'option_key' => '',
        ];
        $groups[] = [
            'id' => 'invalid',
            'option_key' => '',
        ];

        return $groups;
    }

    /**
     * Registers some example settings.
     *
     * @param array $settings
     *
     * @return array
     *
     * @since 3.0.0
     */
    public static function register_test_settings($settings)
    {
        $settings[] = [
            'id' => 'woocommerce_shop_page_display',
            'label' => 'Shop page display',
            'description' => 'This controls what is shown on the product archive.',
            'default' => '',
            'type' => 'select',
            'options' => [
                '' => 'Show products',
                'subcategories' => 'Show categories &amp; subcategories',
                'both' => 'Show both',
            ],
            'option_key' => 'woocommerce_shop_page_display',
        ];

        return $settings;
    }
}
