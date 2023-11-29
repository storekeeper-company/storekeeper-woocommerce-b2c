<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Exceptions\ShippingMethodImportException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\WithConsoleProgressBarInterface;
use StoreKeeper\WooCommerce\B2C\Models\ShippingMethodModel;
use StoreKeeper\WooCommerce\B2C\Models\ShippingZoneModel;
use StoreKeeper\WooCommerce\B2C\Traits\ConsoleProgressBarTrait;

class ShippingMethodImport extends AbstractImport implements WithConsoleProgressBarInterface
{
    use ConsoleProgressBarTrait;

    const SHIPPING_ZONE_NAME_PREFIX = 'SK_';
    const SHIPPING_LOCATION_TYPE = 'country';

    const SHIPPING_CLASS_FLAT_RATE = 'flat_rate';
    const SHIPPING_CLASS_FREE_SHIPPING = 'free_shipping';
    const SHIPPING_CLASS_LOCAL_PICKUP = 'local_pickup';

    const FREE_SHIPPING_REQUIRES = 'min_amount';

    const SK_SHIPPING_TYPE_ALIAS_PARCEL = 'Parcel';
    const SK_SHIPPING_TYPE_ALIAS_TRUCK_DELIVERY = 'TruckDelivery';
    const SK_SHIPPING_TYPE_ALIAS_PICKUP_AT_STORE = 'PickupAtStore';

    const SK_SHIPPING_TYPE_MODULE = 'ShippingModule';

    private int $storekeeper_id = 0;

    public function __construct(array $settings = [])
    {
        $this->storekeeper_id = array_key_exists('storekeeper_id', $settings) ? (int) $settings['storekeeper_id'] : 0;
        unset($settings['storekeeper_id']);
        parent::__construct($settings);
    }

    protected function getModule()
    {
        return 'ShopModule';
    }

    protected function getFunction()
    {
        return 'listShippingMethodsForHooks';
    }

    protected function getFilters()
    {
        $filters = [
            [
                'name' => 'enabled__=',
                'val' => '1',
            ],
            [
                'name' => 'is_system__=',
                'val' => '0',
            ],
        ];

        if ($this->storekeeper_id > 0) {
            $filters[] = [
                'name' => 'id__=',
                'val' => $this->storekeeper_id,
            ];
        }

        return $filters;
    }

    protected function getLanguage()
    {
        return null;
    }

    /**
     * @throws ShippingMethodImportException
     * @throws WordpressException
     */
    protected function processItem(Dot $dotObject, array $options = [])
    {
        $this->debug('Processing shipping method', $dotObject->get());
        $storekeeperId = $dotObject->get('id');

        if (!$this->isShippingTypeValid($dotObject)) {
            $this->debug("Unsupported shipping type {$dotObject->get('shipping_type.alias')}", [
                'storeKeeperId' => $storekeeperId,
            ]);
            // TODO: Skip or throw error?
            throw new ShippingMethodImportException("Unsupported shipping type {$dotObject->get('shipping_type.alias')}");
        }
        $country_iso2s = $dotObject->has('country_iso2s') ? $dotObject->get('country_iso2s') : [];

        if (empty($country_iso2s)) {
            $ShopModule = $this->storekeeper_api->getModule('ShopModule');
            $response = $ShopModule->getShopWithRelation();
            $shopData = new Dot($response);
            $defaultCountryIso2 = $shopData->get('relation_data.business_data.country_iso2');
            $country_iso2s[] = $defaultCountryIso2;
        }

        foreach ($country_iso2s as $country_iso2) {
            $shippingZone = ShippingZoneModel::getByCountryIso2($country_iso2);
            if (empty($shippingZone)) {
                $this->debug('No shipping zone found, will attempt to create one', [
                    'storeKeeperId' => $storekeeperId,
                    'countryIso2' => $country_iso2,
                ]);
                $wcShippingZone = new \WC_Shipping_Zone();
                $wcShippingZone->set_zone_name(self::SHIPPING_ZONE_NAME_PREFIX.strtoupper($country_iso2));
                if (!WC()->countries->country_exists(strtoupper($country_iso2))) {
                    $this->debug('Country is not supported on this webshop', [
                        'storeKeeperId' => $storekeeperId,
                        'countryIso2' => $country_iso2,
                    ]);
                    throw new ShippingMethodImportException("Country code '$country_iso2' is not valid or not allowed in WooCommerce settings");
                }
                $wcShippingZone->set_locations([
                    [
                        'code' => strtoupper($country_iso2),
                        'type' => self::SHIPPING_LOCATION_TYPE,
                    ],
                ]);
                $wcShippingZoneId = $wcShippingZone->save();
                $shippingZoneId = ShippingZoneModel::create([
                   'wc_zone_id' => $wcShippingZoneId,
                   'country_iso2' => $country_iso2,
                ]);

                $this->debug('Successfully created a new zone', [
                    'storeKeeperId' => $storekeeperId,
                    'wcShippingZoneId' => $wcShippingZoneId,
                    'countryIso2' => $country_iso2,
                ]);
            } else {
                $wcShippingZoneId = $shippingZone['wc_zone_id'];
                $wcShippingZone = new \WC_Shipping_Zone($wcShippingZoneId);
                $shippingZoneId = (int) $shippingZone['id'];

                $this->debug('Shipping zone found', [
                    'storeKeeperId' => $storekeeperId,
                    'wcShippingZoneId' => $wcShippingZoneId,
                    'countryIso2' => $country_iso2,
                ]);
            }

            $wcShippingMethodInstanceId = ShippingMethodModel::getInstanceIdByStorekeeperZoneAndId($shippingZoneId, $storekeeperId);

            if (is_null($wcShippingMethodInstanceId)) {
                $this->debug('No shipping method for zone, will attempt to create one', [
                    'storeKeeperId' => $storekeeperId,
                    'countryIso2' => $country_iso2,
                    'wcShippingZoneId' => $wcShippingZoneId,
                ]);
                $wcShippingMethodType = $this->getWoocommerceShippingMethodType($dotObject);
                $wcShippingMethodInstanceId = $wcShippingZone->add_shipping_method($wcShippingMethodType);
                $wcShippingMethodInstance = \WC_Shipping_Zones::get_shipping_method($wcShippingMethodInstanceId);
                ShippingMethodModel::create([
                    'wc_instance_id' => $wcShippingMethodInstanceId,
                    'storekeeper_id' => $storekeeperId,
                    'sk_zone_id' => $shippingZoneId,
                ]);

                $this->debug('Successfully created a new zone', [
                    'storeKeeperId' => $storekeeperId,
                    'wcShippingZoneId' => $wcShippingZoneId,
                    'countryIso2' => $country_iso2,
                    'wcShippingInstanceId' => $wcShippingMethodInstanceId,
                ]);
            } else {
                $wcShippingMethodInstance = \WC_Shipping_Zones::get_shipping_method($wcShippingMethodInstanceId);
                $wcShippingMethodType = $wcShippingMethodInstance->id;

                $this->debug('Shipping zone found', [
                    'storeKeeperId' => $storekeeperId,
                    'wcShippingZoneId' => $wcShippingZoneId,
                    'wcShippingMethodInstanceId' => $wcShippingMethodInstanceId,
                    'countryIso2' => $country_iso2,
                ]);
            }

            switch ($wcShippingMethodType) {
                case self::SHIPPING_CLASS_FLAT_RATE:
                    $this->debug('Setting correct data for shipping method instance', [
                        'storeKeeperId' => $storekeeperId,
                        'wcShippingMethodInstanceId' => $wcShippingMethodInstanceId,
                        'shippingMethodType' => self::SHIPPING_CLASS_FLAT_RATE,
                    ]);
                    /* @var \WC_Shipping_Flat_Rate $wcShippingMethodInstance */
                    $wcShippingMethodInstance->init_instance_settings();
                    $wcShippingMethodInstance->instance_settings['cost'] = (string) $dotObject->get('shipping_method_price_flat_strategy.ppu_wt');
                    $wcShippingMethodInstance->instance_settings['title'] = (string) $dotObject->get('name');
                    $this->saveShippingMethodInstance($wcShippingMethodInstance);
                    break;
                case self::SHIPPING_CLASS_LOCAL_PICKUP:
                    $this->debug('Setting correct data for shipping method instance', [
                        'storeKeeperId' => $storekeeperId,
                        'wcShippingMethodInstanceId' => $wcShippingMethodInstanceId,
                        'shippingMethodType' => self::SHIPPING_CLASS_LOCAL_PICKUP,
                    ]);
                    /* @var \WC_Shipping_Local_Pickup $wcShippingMethodInstance */
                    $wcShippingMethodInstance->init_instance_settings();
                    $wcShippingMethodInstance->instance_settings['cost'] = (string) $dotObject->get('shipping_method_price_flat_strategy.ppu_wt');
                    $wcShippingMethodInstance->instance_settings['title'] = (string) $dotObject->get('name');
                    $this->saveShippingMethodInstance($wcShippingMethodInstance);
                    break;
                case self::SHIPPING_CLASS_FREE_SHIPPING:
                default:
                    $this->debug('Setting correct data for shipping method instance', [
                        'storeKeeperId' => $storekeeperId,
                        'wcShippingMethodInstanceId' => $wcShippingMethodInstanceId,
                        'shippingMethodType' => self::SHIPPING_CLASS_FREE_SHIPPING,
                    ]);
                    /* @var \WC_Shipping_Free_Shipping $wcShippingMethodInstance */
                    $wcShippingMethodInstance->init_instance_settings();
                    $wcShippingMethodInstance->instance_settings['min_amount'] = (int) $dotObject->get('shipping_method_price_flat_strategy.free_from_value_wt');
                    $wcShippingMethodInstance->instance_settings['title'] = (string) $dotObject->get('name');
                    $wcShippingMethodInstance->instance_settings['requires'] = self::FREE_SHIPPING_REQUIRES;
                    $this->saveShippingMethodInstance($wcShippingMethodInstance);
                    break;
            }
        }
    }

    protected function getImportEntityName(): string
    {
        return __('shipping methods', I18N::DOMAIN);
    }

    private function isShippingTypeValid(Dot $dotObject): bool
    {
        $isValid = false;
        $wcShippingMethodClassNames = WC()->shipping()->get_shipping_method_class_names();
        $mappedShippingMethodType = $this->getWoocommerceShippingMethodType($dotObject);
        if (!is_null($mappedShippingMethodType) && array_key_exists($mappedShippingMethodType, $wcShippingMethodClassNames)) {
            $isValid = true;
        }

        return self::SK_SHIPPING_TYPE_MODULE === $dotObject->get('shipping_type.module_name') && $isValid;
    }

    private function getWoocommerceShippingMethodType(Dot $dotObject): ?string
    {
        if (0 === $dotObject->get('shipping_method_price_flat_strategy.ppu_wt') && $dotObject->has('shipping_method_price_flat_strategy.free_from_value_wt')) {
            return self::SHIPPING_CLASS_FREE_SHIPPING;
        }

        $shippingClassMapping = [
            self::SK_SHIPPING_TYPE_ALIAS_PARCEL => self::SHIPPING_CLASS_FLAT_RATE,
            self::SK_SHIPPING_TYPE_ALIAS_PICKUP_AT_STORE => self::SHIPPING_CLASS_LOCAL_PICKUP,
            self::SK_SHIPPING_TYPE_ALIAS_TRUCK_DELIVERY => self::SHIPPING_CLASS_FLAT_RATE,
        ];

        $shippingTypeAlias = $dotObject->get('shipping_type.alias');

        return $shippingClassMapping[$shippingTypeAlias] ?? null;
    }

    /**
     * @see \WC_Shipping_Method::process_admin_options()
     */
    private function saveShippingMethodInstance(\WC_Shipping_Method $wcShippingMethodInstance): bool
    {
        return update_option($wcShippingMethodInstance->get_instance_option_key(), apply_filters('woocommerce_shipping_'.$wcShippingMethodInstance->id.'_instance_settings_values', $wcShippingMethodInstance->instance_settings, $this), 'yes');
    }
}
