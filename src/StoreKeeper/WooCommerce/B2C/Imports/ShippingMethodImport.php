<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Exceptions\ShippingMethodImportException;
use StoreKeeper\WooCommerce\B2C\Exceptions\TableOperationSqlException;
use StoreKeeper\WooCommerce\B2C\Exceptions\UnsupportedShippingMethodTypeException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\WithConsoleProgressBarInterface;
use StoreKeeper\WooCommerce\B2C\Models\ShippingMethodModel;
use StoreKeeper\WooCommerce\B2C\Models\ShippingZoneModel;
use StoreKeeper\WooCommerce\B2C\Traits\ConsoleProgressBarTrait;

class ShippingMethodImport extends AbstractImport implements WithConsoleProgressBarInterface
{
    use ConsoleProgressBarTrait;

    public const SHIPPING_ZONE_NAME_PREFIX = 'SK_';
    public const SHIPPING_LOCATION_TYPE = 'country';

    public const SHIPPING_CLASS_FLAT_RATE = 'flat_rate';
    public const SHIPPING_CLASS_FREE_SHIPPING = 'free_shipping';
    public const SHIPPING_CLASS_LOCAL_PICKUP = 'local_pickup';

    public const FREE_SHIPPING_REQUIRES = 'min_amount';

    public const SK_SHIPPING_TYPE_ALIAS_PARCEL = 'Parcel';
    public const SK_SHIPPING_TYPE_ALIAS_TRUCK_DELIVERY = 'TruckDelivery';
    public const SK_SHIPPING_TYPE_ALIAS_PICKUP_AT_STORE = 'PickupAtStore';

    public const SK_SHIPPING_TYPE_MODULE = 'ShippingModule';

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
        return [
            [
                'name' => 'is_system__=',
                'val' => '0',
            ],
        ];
    }

    protected function getLanguage()
    {
        return null;
    }

    /**
     * @throws ShippingMethodImportException
     * @throws WordpressException
     */
    protected function processItem(Dot $dotObject, array $options = []): ?int
    {
        $this->debug('Processing shipping method', $dotObject->get());
        $storekeeperId = $dotObject->get('id');

        if (!$dotObject->get('enabled')) {
            if (ShippingMethodModel::methodExists($storekeeperId)) {
                $this->deleteShippingMethodAndOrphanedShippingZones($storekeeperId);
            } else {
                $this->debug('Shipping method is disabled, it will be skipped', [
                    'storeKeeperId' => $storekeeperId,
                ]);
            }
        } elseif ($this->isShippingTypeValid($dotObject) || ShippingMethodModel::methodExists($storekeeperId)) {
            $this->processShippingMethod($dotObject);
        } else {
            $this->debug("Unsupported shipping type {$dotObject->get('shipping_type.alias')}, will be skipped.", [
                'storeKeeperId' => $storekeeperId,
            ]);
        }

        return $storekeeperId;
    }

    public function deleteShippingMethodAndOrphanedShippingZones(int $storeKeeperId): void
    {
        $skShippingZoneIds = ShippingMethodModel::getShippingZoneIdsByStoreKeeperId($storeKeeperId);

        if (!is_null($skShippingZoneIds)) {
            $findByIds = implode(',', $skShippingZoneIds);
            $skShippingZones = ShippingZoneModel::findBy(
                ["id IN ($findByIds)"],
            );

            if (!empty($skShippingZones)) {
                foreach ($skShippingZones as $skShippingZone) {
                    $wcShippingMethodInstanceId = ShippingMethodModel::getInstanceIdByShippingZoneAndStoreKeeperId(
                        $skShippingZone['id'],
                        $storeKeeperId
                    );

                    $woocommerceZone = new \WC_Shipping_Zone($skShippingZone['wc_zone_id']);
                    $woocommerceZone->delete_shipping_method($wcShippingMethodInstanceId);
                    $woocommerceZone->save();
                    if (0 === count($woocommerceZone->get_shipping_methods())) {
                        $woocommerceZone->delete(true);
                    }
                }
            }
        }
    }

    private function processShippingMethod(Dot $dotObject): void
    {
        $storekeeperId = $dotObject->get('id');
        $countryIso2s = $dotObject->has('country_iso2s') ? $dotObject->get('country_iso2s') : [];

        if (empty($countryIso2s)) {
            $ShopModule = $this->storekeeper_api->getModule('ShopModule');
            $response = $ShopModule->getShopWithRelation();
            $shopData = new Dot($response);
            $defaultCountryIso2 = $shopData->get('relation_data.business_data.country_iso2');
            $countryIso2s[] = $defaultCountryIso2;
        }

        $this->removeOrphanedShippingZonesByMethodCountry($storekeeperId, $countryIso2s);

        foreach ($countryIso2s as $countryIso2) {
            /* @var \WC_Shipping_Zone $wcShippingZone */
            [$wcShippingZone, $skZoneId] = $this->ensureShippingZone($countryIso2, $dotObject);
            try {
                $wcShippingMethodInstance = $this->ensureShippingMethod($countryIso2, $skZoneId, $wcShippingZone, $dotObject);
                switch ($wcShippingMethodInstance->id) {
                    case self::SHIPPING_CLASS_FLAT_RATE:
                        $this->debug('Setting correct data for shipping method instance', [
                            'storeKeeperId' => $storekeeperId,
                            'wcShippingMethodInstanceId' => $wcShippingMethodInstance->get_instance_id(),
                            'shippingMethodType' => self::SHIPPING_CLASS_FLAT_RATE,
                        ]);
                        /* @var \WC_Shipping_Flat_Rate $wcShippingMethodInstance */
                        $wcShippingMethodInstance->init_instance_settings();
                        $wcShippingMethodInstance->instance_settings['min_amount'] = floatval($dotObject->get('shipping_method_price_flat_strategy.free_from_value_wt'));
                        $wcShippingMethodInstance->instance_settings['cost'] = (string) $dotObject->get('shipping_method_price_flat_strategy.ppu_wt');
                        $wcShippingMethodInstance->instance_settings['title'] = (string) $dotObject->get('name');
                        $this->saveShippingMethodInstance($wcShippingMethodInstance);
                        break;
                    case self::SHIPPING_CLASS_LOCAL_PICKUP:
                        $this->debug('Setting correct data for shipping method instance', [
                            'storeKeeperId' => $storekeeperId,
                            'wcShippingMethodInstanceId' => $wcShippingMethodInstance->get_instance_id(),
                            'shippingMethodType' => self::SHIPPING_CLASS_LOCAL_PICKUP,
                        ]);
                        /* @var \WC_Shipping_Local_Pickup $wcShippingMethodInstance */
                        $wcShippingMethodInstance->init_instance_settings();
                        $wcShippingMethodInstance->instance_settings['min_amount'] = floatval($dotObject->get('shipping_method_price_flat_strategy.free_from_value_wt'));
                        $wcShippingMethodInstance->instance_settings['cost'] = (string) $dotObject->get('shipping_method_price_flat_strategy.ppu_wt');
                        $wcShippingMethodInstance->instance_settings['title'] = (string) $dotObject->get('name');
                        $this->saveShippingMethodInstance($wcShippingMethodInstance);
                        break;
                    case self::SHIPPING_CLASS_FREE_SHIPPING:
                    default:
                        $this->debug('Setting correct data for shipping method instance', [
                            'storeKeeperId' => $storekeeperId,
                            'wcShippingMethodInstanceId' => $wcShippingMethodInstance->get_instance_id(),
                            'shippingMethodType' => self::SHIPPING_CLASS_FREE_SHIPPING,
                        ]);
                        /* @var \WC_Shipping_Free_Shipping $wcShippingMethodInstance */
                        $wcShippingMethodInstance->init_instance_settings();
                        $wcShippingMethodInstance->instance_settings['min_amount'] = floatval($dotObject->get('shipping_method_price_flat_strategy.free_from_value_wt'));
                        $wcShippingMethodInstance->instance_settings['title'] = (string) $dotObject->get('name');
                        $wcShippingMethodInstance->instance_settings['requires'] = self::FREE_SHIPPING_REQUIRES;
                        $this->saveShippingMethodInstance($wcShippingMethodInstance);
                        break;
                }
            } catch (UnsupportedShippingMethodTypeException $exception) {
                $this->debug("Unsupported shipping type {$dotObject->get('shipping_type.alias')}.", [
                    'storeKeeperId' => $storekeeperId,
                ]);
                // Remove the woocommerce zone just in case it was orphaned
                // because of changing into an unsupported type
                if (0 === count($wcShippingZone->get_shipping_methods())) {
                    $wcShippingZone->delete(true);
                }
            }
        }
    }

    private function ensureShippingZone(string $countryIso2, Dot $dotObject): array
    {
        $storekeeperId = $dotObject->get('id');
        $shippingZone = ShippingZoneModel::getByCountryIso2($countryIso2);
        if (empty($shippingZone)) {
            $this->debug('No shipping zone found, will attempt to create one', [
                'storeKeeperId' => $storekeeperId,
                'countryIso2' => $countryIso2,
            ]);
            $wcShippingZone = new \WC_Shipping_Zone();
            $wcShippingZone->set_zone_name(self::SHIPPING_ZONE_NAME_PREFIX.strtoupper($countryIso2));
            if (!WC()->countries->country_exists(strtoupper($countryIso2))) {
                $this->debug('Country is not supported on this webshop', [
                    'storeKeeperId' => $storekeeperId,
                    'countryIso2' => $countryIso2,
                ]);
                throw new ShippingMethodImportException("Country code '$countryIso2' is not valid or not allowed in WooCommerce settings");
            }
            $wcShippingZone->set_locations([
                [
                    'code' => strtoupper($countryIso2),
                    'type' => self::SHIPPING_LOCATION_TYPE,
                ],
            ]);
            $wcShippingZoneId = $wcShippingZone->save();
            $shippingZoneId = ShippingZoneModel::create([
                'wc_zone_id' => $wcShippingZoneId,
                'country_iso2' => $countryIso2,
            ]);

            $this->debug('Successfully created a new zone', [
                'storeKeeperId' => $storekeeperId,
                'wcShippingZoneId' => $wcShippingZoneId,
                'countryIso2' => $countryIso2,
            ]);
        } else {
            $wcShippingZoneId = $shippingZone['wc_zone_id'];
            $wcShippingZone = new \WC_Shipping_Zone($wcShippingZoneId);
            $shippingZoneId = (int) $shippingZone['id'];

            $this->debug('Shipping zone found', [
                'storeKeeperId' => $storekeeperId,
                'wcShippingZoneId' => $wcShippingZoneId,
                'countryIso2' => $countryIso2,
            ]);
        }

        return [$wcShippingZone, $shippingZoneId];
    }

    /**
     * @throws UnsupportedShippingMethodTypeException
     * @throws TableOperationSqlException
     */
    private function ensureShippingMethod(string $countryIso2, int $skZoneId, \WC_Shipping_Zone $wcShippingZone, Dot $dotObject)
    {
        $storekeeperId = $dotObject->get('id');
        $wcShippingMethodInstanceId = ShippingMethodModel::getInstanceIdByShippingZoneAndStoreKeeperId($skZoneId, $storekeeperId);

        if (is_null($wcShippingMethodInstanceId)) {
            $this->debug('No shipping method for zone, will attempt to create one', [
                'storeKeeperId' => $storekeeperId,
                'countryIso2' => $countryIso2,
                'wcShippingZoneId' => $wcShippingZone->get_id(),
            ]);
            $wcShippingMethodInstance = $this->createNewShippingMethodInstance($dotObject, $wcShippingZone, $skZoneId, $countryIso2);
        } else {
            $wcShippingMethodInstance = \WC_Shipping_Zones::get_shipping_method($wcShippingMethodInstanceId);

            $this->debug('Shipping zone found', [
                'storeKeeperId' => $storekeeperId,
                'wcShippingZoneId' => $wcShippingZone->get_id(),
                'wcShippingMethodInstanceId' => $wcShippingMethodInstanceId,
                'countryIso2' => $countryIso2,
            ]);

            if ($this->getWoocommerceShippingMethodType($dotObject) !== $wcShippingMethodInstance->id) {
                // This means the type was updated on the backoffice/backend, so we have to process.
                $wcShippingZone->delete_shipping_method($wcShippingMethodInstance->get_instance_id());
                $wcShippingMethodInstance = $this->createNewShippingMethodInstance($dotObject, $wcShippingZone, $skZoneId, $countryIso2);
            }
        }

        return $wcShippingMethodInstance;
    }

    /**
     * @throws UnsupportedShippingMethodTypeException
     * @throws TableOperationSqlException
     */
    private function createNewShippingMethodInstance(Dot $dotObject, \WC_Shipping_Zone $wcShippingZone, int $skZoneId, string $countryIso2)
    {
        $storekeeperId = $dotObject->get('id');

        $wcShippingMethodType = $this->getWoocommerceShippingMethodType($dotObject);

        if (is_null($wcShippingMethodType)) {
            throw new UnsupportedShippingMethodTypeException('Shipping method alias'.$dotObject->get('shipping_type.alias').' is not supported currently.');
        }

        $wcShippingMethodInstanceId = $wcShippingZone->add_shipping_method($wcShippingMethodType);
        $wcShippingMethodInstance = \WC_Shipping_Zones::get_shipping_method($wcShippingMethodInstanceId);
        ShippingMethodModel::create([
            'wc_instance_id' => $wcShippingMethodInstanceId,
            'storekeeper_id' => $storekeeperId,
            'sk_zone_id' => $skZoneId,
        ]);

        $this->debug('Successfully created a new zone', [
            'storeKeeperId' => $storekeeperId,
            'wcShippingZoneId' => $wcShippingZone->get_id(),
            'countryIso2' => $countryIso2,
            'wcShippingInstanceId' => $wcShippingMethodInstanceId,
        ]);

        return $wcShippingMethodInstance;
    }

    protected function afterRun(array $storeKeeperIds)
    {
        // We have this after run clean up for disabled methods that are no longer returned on listShippingMethodsForHooks
        $whereClauses = [];
        if (!empty($storeKeeperIds)) {
            $implodedStoreKeeperIds = implode(',', $storeKeeperIds);
            $whereClauses[] = "storekeeper_id NOT IN ($implodedStoreKeeperIds)";
        }
        $missingStoreKeeperMethodIds = ShippingMethodModel::getUniqueStoreKeeperIds(
            $whereClauses,
        );

        if (!is_null($missingStoreKeeperMethodIds)) {
            foreach ($missingStoreKeeperMethodIds as $missingStoreKeeperMethodId) {
                $this->deleteShippingMethodAndOrphanedShippingZones($missingStoreKeeperMethodId);
            }
        }

        parent::afterRun($storeKeeperIds);
    }

    protected function getImportEntityName(): string
    {
        return __('shipping methods', I18N::DOMAIN);
    }

    private function removeOrphanedShippingZonesByMethodCountry(int $storeKeeperId, array $countryIso2s): void
    {
        $skShippingZoneIds = ShippingMethodModel::getShippingZoneIdsByStoreKeeperId($storeKeeperId);

        if (!is_null($skShippingZoneIds)) {
            $findByIds = implode(',', $skShippingZoneIds);
            $skShippingZones = ShippingZoneModel::findBy(
                ["id IN ($findByIds)"],
            );

            if (!empty($skShippingZones)) {
                foreach ($skShippingZones as $skShippingZone) {
                    if (!in_array($skShippingZone['country_iso2'], $countryIso2s, true)) {
                        $wcShippingMethodInstanceId = ShippingMethodModel::getInstanceIdByShippingZoneAndStoreKeeperId(
                            $skShippingZone['id'],
                            $storeKeeperId
                        );

                        $woocommerceZone = new \WC_Shipping_Zone($skShippingZone['wc_zone_id']);
                        $woocommerceZone->delete_shipping_method($wcShippingMethodInstanceId);
                        $woocommerceZone->save();

                        if (0 === count($woocommerceZone->get_shipping_methods())) {
                            $woocommerceZone->delete(true);
                        }
                    }
                }
            }
        }
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
