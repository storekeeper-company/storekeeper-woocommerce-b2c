<?php

namespace StoreKeeper\WooCommerce\B2C\Frontend\Handlers;

use StoreKeeper\WooCommerce\B2C\Hooks\WithHooksInterface;
use StoreKeeper\WooCommerce\B2C\Models\ShippingMethodModel;
use StoreKeeper\WooCommerce\B2C\Models\ShippingZoneModel;
use StoreKeeper\WooCommerce\B2C\Imports\ShippingMethodImport;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\AddressModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\ShippingSettingModel;
use StoreKeeper\WooCommerce\B2C\Core;
use Adbar\Dot;

class LocationShippingHandler implements WithHooksInterface
{

    /**
     * Shipping methods ID map (`wc_instance_id` -> `storekeeper_id`)
     *
     * @var null|array
     */
    protected $shippingMethodsIdMap;

    /**
     * Shipping zone country map (`wc_instance_id` -> `country_iso2`)
     *
     * @var null|array
     */
    protected $shippingZoneMap;

    /**
     * Register hooks
     *
     * @return void
     */
    public function registerHooks(): void
    {
        add_action('woocommerce_after_shipping_rate', \Closure::fromCallable([$this, 'renderLocations']));
        add_action('woocommerce_checkout_update_order_review', \Closure::fromCallable([$this, 'updateChosenLocation']));

        add_action('wp_enqueue_scripts', \Closure::fromCallable([$this, 'enqueueScripts']));
    }

    /**
     * Update chosen locations
     *
     * @param string $postData
     * @return void
     */
    protected function updateChosenLocation($postData)
    {
        $data = [];

        parse_str($postData, $data);

        $item = new Dot($data['storekeeper'] ?? []);

        WC()->session->set('chosen_storekeeper_location', array_map('intval', $item->get('location.shipping_method')));
    }

    /**
     * Render locations
     *
     * @param \WC_Shipping_Rate $rate
     * @return void
     */
    protected function renderLocations($rate)
    {
        if (in_array($rate->get_method_id(), $this->getSupportedMethodIds()) &&
            $this->isStoreKeeperShippingMethod((int) $rate->get_instance_id())) {
            $chosenShippingMethods = WC()->session->get('chosen_shipping_methods') ?? [];
            if (in_array($rate->get_id(), $chosenShippingMethods) &&
                $locations = $this->getLocations($rate)) {
                echo wc_get_template_html(
                    'cart/cart-shipping/location.php',
                    [
                        'locations' => $locations,
                        'rate' => $rate
                    ],
                    'storekeeper',
                    Core::plugin_abspath() . 'templates/'
                );
            }
        }
    }

    /**
     * Retrieve locations based on provided rate
     *
     * @param \WC_Shipping_Rate $rate
     * @return array
     */
    protected function getLocations($rate)
    {
        global $wpdb;

        $select = LocationModel::getSelectHelper('location')
            ->cols([
                sprintf('location.%s', LocationModel::PRIMARY_KEY),
                'location.name'
            ])
            ->innerJoin(
                sprintf('%s AS shipping_setting', ShippingSettingModel::getTableName()),
                sprintf(
                    'shipping_setting.location_id = location.%s',
                    LocationModel::PRIMARY_KEY
                )
            )
            ->where('location.is_active = 1');

        if (ShippingMethodImport::SHIPPING_CLASS_FLAT_RATE === $rate->get_method_id()) {
            $countryId = $this->getStoreKeeperShippingZoneCountry($rate->get_instance_id());

            if (null !== $countryId) {
                $select
                    ->innerJoin(
                        sprintf('%s AS address', AddressModel::getTableName()),
                        sprintf(
                            'address.location_id = location.%s',
                            LocationModel::PRIMARY_KEY
                        )
                    )
                    ->where('address.country = :country')
                    ->bindValue('country', $countryId);
            }

            $select->where('shipping_setting.is_truck_delivery_next_day > 0');
        } else {
            $select->where('shipping_setting.is_pickup_next_day > 0');
        }

        return $wpdb->get_results(LocationModel::prepareQuery($select), ARRAY_A);
    }

    /**
     * Detect whether is StoreKeeper's shipping method
     *
     * @param int $wcInstanceId
     * @return bool
     */
    protected function isStoreKeeperShippingMethod($wcInstanceId)
    {
        return array_key_exists($wcInstanceId, $this->getStoreKeeperShippingMethodIdMap());
    }

    /**
     * Get StoreKeeper's shipping method data map
     *
     * @return array
     */
    protected function getStoreKeeperShippingMethodIdMap()
    {
        global $wpdb;

        if (null === $this->shippingMethodsIdMap) {
            $mapSelectQuery = ShippingMethodModel::getSelectHelper()
                ->cols(['wc_instance_id', 'storekeeper_id']);

            $this->shippingMethodsIdMap = array_reduce(
                $wpdb->get_results(ShippingMethodModel::prepareQuery($mapSelectQuery), ARRAY_A),
                function (array $map, $shippingMethod) {
                    $map[$shippingMethod['wc_instance_id']] = $shippingMethod['storekeeper_id'];

                    return $map;
                },
                []
            );

            unset($mapSelectQuery);
        }

        return $this->shippingMethodsIdMap;
    }

    /**
     * Get StoreKeeper's shipping zone country map
     *
     * @param null|int $wcInstanceId
     * @return null|string|array
     */
    protected function getStoreKeeperShippingZoneCountry($wcInstanceId = null)
    {
        global $wpdb;

        if (null === $this->shippingZoneMap) {
            $mapSelectQuery = ShippingMethodModel::getSelectHelper('shipping_method')
                ->cols(['shipping_method.wc_instance_id', 'shipping_zone.country_iso2'])
                ->leftJoin(
                    sprintf('%s AS shipping_zone', ShippingZoneModel::getTableName()),
                    sprintf(
                        'shipping_zone.%s = shipping_method.sk_zone_id',
                        ShippingZoneModel::PRIMARY_KEY
                    )
                );

            $this->shippingZoneMap = array_reduce(
                $wpdb->get_results(ShippingMethodModel::prepareQuery($mapSelectQuery), ARRAY_A),
                function (array $map, $shippingMethod) {
                    $map[$shippingMethod['wc_instance_id']] = $shippingMethod['country_iso2'];

                    return $map;
                },
                []
            );
        }

        if (null !== $wcInstanceId) {
            if (array_key_exists($wcInstanceId, $this->shippingZoneMap)) {
                return $this->shippingZoneMap[$wcInstanceId];
            }

            return null;
        }

        return $this->shippingZoneMap;
    }

    /**
     * Update chosen locations
     *
     * @param string $postData
     * @return void
     */
    protected function enqueueScripts()
    {
        if (\is_checkout() && !\WC_Blocks_Utils::has_block_in_page(\get_the_ID(), 'woocommerce/checkout')) {
            wp_enqueue_script(
                'sk-checkout-location',
                Core::plugin_url() . '/resources/js/checkout/location.js',
                [
                    'wc-checkout'
                ],
                false
            );
        }
    }

    /**
     * Get supported shipping method IDs
     *
     * @return string[]
     */
    protected function getSupportedMethodIds()
    {
        return [
            ShippingMethodImport::SHIPPING_CLASS_FLAT_RATE,
            ShippingMethodImport::SHIPPING_CLASS_LOCAL_PICKUP
        ];
    }
}
