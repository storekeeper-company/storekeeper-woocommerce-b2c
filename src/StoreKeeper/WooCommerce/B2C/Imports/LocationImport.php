<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\AddressModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningHourModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningSpecialHoursModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\ShippingSettingModel;
use StoreKeeper\WooCommerce\B2C\Cache\LocationShippingCache;

class LocationImport extends AbstractImport
{

    /**
     * @var null|int
     */
    protected $storekeeper_id;

    /**
     * @var array
     */
    protected $addressMap = [
        'city' => 'address.city',
        'zipcode' => 'address.zipcode',
        'state' => 'address.state',
        'phone'=> 'address.contact_set.phone',
        'email'=> 'address.contact_set.email',
        'url' => 'address.contact_set.www',
        'street' => 'address.street',
        'streetnumber' => 'address.streetnumber',
        'flatnumber' => 'address.flatnumber',
        'country' => 'address.country_iso2',
        'gln' => 'address.gln',
        'published' => 'address.published'
    ];

    /**
     * @var null|array
     */
    protected $daysOfWeek;

    /**
     * @var array
     */
    protected $locationOpeningHours = [];

    /**
     * @var array
     */
    protected $locationSpecialOpeningHoursIdMap = [];

    /**
     * Location constructor.
     *
     * @param array $settings
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        if (array_key_exists('storekeeper_id', $settings)) {
            $this->storekeeper_id = absint($settings['storekeeper_id']);
            unset($settings['storekeeper_id']);
        }

        parent::__construct($settings);
    }

    /**
     * @return string
     */
    protected function getModule()
    {
        return 'ShopModule';
    }

    /**
     * @return string
     */
    protected function getFunction()
    {
        return 'listLocationsForHook';
    }

    /**
     * @return null
     */
    protected function getLanguage()
    {
        return null;
    }

    /**
     * @return array
     */
    protected function getFilters()
    {
        $filters = [];
        if (null !== $this->storekeeper_id) {
            $filters[] = [
                'name' => 'id__=',
                'val' => $this->storekeeper_id,
            ];
        }

        return $filters;
    }

    /**
     * @param Dot $dotObject
     * @param array $options
     * @throws \Exception
     */
    protected function processItem($dotObject, array $options = []): ?int
    {
        $locationId = $this->processLocation($dotObject);

        if (null !== $locationId) {
            $this->processLocationAddress($dotObject, $locationId);
            $this->processLocationOpeningHours($dotObject, $locationId);
            $this->processLocationSpecialOpeningHours($dotObject, $locationId);
            $this->processLocationShippingSetting($dotObject, $locationId);

            return LocationModel::getStoreKeeperId($locationId);
        }

        unset($locationId);

        return null;
    }

    /**
     * Get StoreKeeper location identifier
     *
     * @param Dot $dotObject
     * @return int
     * @throws \Exception
     */
    protected function getStoreKeeperId(Dot $dotObject): int
    {
        if (null === $dotObject->get('id') || !($storeKeeperId = (int) $dotObject->get('id'))) {
            throw new \Exception('No location ID provided.');
        }

        return $storeKeeperId;
    }

    /**
     * Process location
     *
     * @param Dot $dotObject
     * @return null|int
     * @throws \Exception
     */
    protected function processLocation(Dot $dotObject): ?int
    {
        global $wpdb;

        $this->debug('Processing location', $dotObject->get());

        $storeKeeperId = $this->getStoreKeeperId($dotObject);

        if ($dotObject->has('deleted') && $dotObject->get('deleted')) {
            $locationId = LocationModel::getLocationIdByStoreKeeperId($storeKeeperId);

            if (null !== $locationId) {
                try {
                    LocationModel::delete($locationId);
                    $this->debug(sprintf('Location id=%d has been successfully deleted.', $storeKeeperId));
                } catch (\Exception $e) {
                    $this->debug(
                        sprintf('Location id=%d couldn\'t be deleted.', $storeKeeperId),
                        [
                            'exception' => $e
                        ]
                    );
                }
            } else {
                $this->debug(sprintf('There is no location id=%d to be deleted.', $storeKeeperId));
            }

            return null;
        }

        if (null === $dotObject->get('name') || '' === ($name = trim((string) $dotObject->get('name')))) {
            throw new \Exception('No location name provided for location id=' . $storeKeeperId);
        }

        $location = LocationModel::getByStoreKeeperId($storeKeeperId);

        $data = [
            'name' => $name,
            'storekeeper_id' => $storeKeeperId
        ];

        if ($dotObject->has('is_default')) {
            $data['is_default'] = (bool) $dotObject->get('is_default');
        }

        if ($dotObject->has('is_active')) {
            $data['is_active'] = (bool) $dotObject->get('is_active');
        }

        $locationId = LocationModel::upsert($data, $location);
        if ($locationId && isset($data['is_default']) && $data['is_default'] &&
            (!$location || !$location['is_default'])) {
            $updateQuery = LocationModel::getUpdateHelper()
                ->cols(['is_default' => false])
                ->where(LocationModel::PRIMARY_KEY.' != :id')
                ->bindValue('id', $locationId);

            $wpdb->query(LocationModel::prepareQuery($updateQuery));

            unset($updateQuery);
        }

        if (null === $location) {
            $this->debug(sprintf('Location id=%d successfully created', $storeKeeperId));
        } else {
            $this->debug(sprintf('Location id=%d successfully updated', $storeKeeperId));
        }

        return $locationId;
    }

    /**
     * Process location address
     *
     * @param Dot $dotObject
     * @param int $locationId
     * @return null|int
     * @throws \Exception
     */
    protected function processLocationAddress(Dot $dotObject, int $locationId): ?int
    {
        global $wpdb;

        $this->debug('Processing location address', $dotObject->get('address'));

        $locationStoreKeeperId = $this->getStoreKeeperId($dotObject);
        if (!$dotObject->has('address')) {
            throw new \Exception('No location address provided for location id=' . $locationStoreKeeperId);
        }

        $addressStoreKeeperId = (int) $dotObject->get('address.id');

        if ($dotObject->has('address.deleted') && $dotObject->get('address.deleted')) {
            $deleteQuery = AddressModel::getDeleteHelper()
                ->where('location_id = :location_id')
                ->where('storekeeper_id = :storekeeper_id')
                ->bindValues([
                    'location_id' => $locationId,
                    'storekeeper_id' => $addressStoreKeeperId
                ]);

            try {
                if ($wpdb->query(AddressModel::prepareQuery($deleteQuery))) {
                    $this->debug(
                        sprintf(
                            'Address id=%d of location id=%s was successfully deleted',
                            $addressStoreKeeperId,
                            $locationStoreKeeperId
                        )
                    );
                } else {
                    $this->debug(
                        sprintf(
                            'There is no address id=%d of location id=%d to be deleted.',
                            $locationStoreKeeperId
                        )
                    );
                }
            } catch (\Exception $e) {
                $this->debug(
                    sprintf(
                        'Address id=%d of location id=%d couldn\'t be deleted.',
                        $addressStoreKeeperId,
                        $locationStoreKeeperId
                    ),
                    [
                        'exception' => $e
                    ]
                );
            }

            unset($deleteQuery);

            return null;
        }

        $deleteQuery = AddressModel::getDeleteHelper()
            ->where('location_id = :location_id')
            ->where('storekeeper_id != :storekeeper_id')
            ->bindValues([
                'location_id' => $locationId,
                'storekeeper_id' => $addressStoreKeeperId
            ]);

        $wpdb->query(AddressModel::prepareQuery($deleteQuery));

        $addressData = AddressModel::getByLocationId($locationId);

        $data = [
            'location_id' => $locationId,
            'storekeeper_id' => $addressStoreKeeperId
        ];

        foreach ($this->addressMap as $field => $path) {
            $data[$field] = $dotObject->get($path);
        }

        $addressId = AddressModel::upsert($data, $addressData);

        if (null === $addressData) {
            $this->debug(
                sprintf(
                    'Address id=%d of location id=%s was successfully created',
                    $addressStoreKeeperId,
                    $locationStoreKeeperId
                )
            );
        } else {
            $this->debug(
                sprintf(
                    'Address id=%d of location id=%s was successfully updated',
                    $addressStoreKeeperId,
                    $locationStoreKeeperId
                )
            );
        }

        return $addressId;
    }

    /**
     * Process location opening hours
     *
     * @param Dot $dotObject
     * @param int $locationId
     * @return LocationImport
     */
    protected function processLocationOpeningHours(Dot $dotObject, int $locationId)
    {
        global $wpdb;

        $this->debug('Processing location opening hours', $dotObject->get('opening_hour'));

        $openDays = array_intersect(
            $this->getDaysOfWeek(),
            array_column($dotObject->get('opening_hour.regular_periods', []), 'open_day')
        );

        $openingHours = $this->getLocationOpeningHours($locationId);

        $deleteQuery = OpeningHourModel::getDeleteHelper()
            ->where('location_id = :location_id')
            ->bindValue('location_id', $locationId);

        if ($openDays) {
            $deleteQuery->where(sprintf('open_day NOT IN(%s)', $this->prepareWhereInValues(array_keys($openDays))));

            $storeKeeperId = (int) $dotObject->get('opening_hour.id');
            foreach ($dotObject->get('opening_hour.regular_periods', []) as $period) {
                $openDay = array_search($period['open_day'], $openDays);
                $openingHour = $openingHours[$openDay] ?? null;

                try {
                    OpeningHourModel::upsert(
                        array_intersect_key(
                            array_merge(
                                $period,
                                [
                                    'open_day' => $openDay,
                                    'location_id' => $locationId,
                                    'storekeeper_id' => $storeKeeperId
                                ]
                            ),
                            OpeningHourModel::getFieldsWithRequired()
                        ),
                        $openingHour
                    );

                    if (null === $openingHour) {
                        $this->debug(
                            sprintf(
                                'The opening hour of location id=%d for the day of \'%s\' has been successfully created',
                                $this->getStoreKeeperId($dotObject),
                                $openDays[$openDay]
                            )
                        );
                    } else {
                        $this->debug(
                            sprintf(
                                'The opening hour of location id=%d for the day of \'%s\' has been successfully updated',
                                $this->getStoreKeeperId($dotObject),
                                $openDays[$openDay]
                            )
                        );
                    }
                } catch (\Exception $e) {
                    if (null === $openingHour) {
                        $this->debug(
                            sprintf(
                                'The opening hour of location id=%d for the day of \'%s\' couldn\'t be created',
                                $this->getStoreKeeperId($dotObject),
                                $openDays[$openDay]
                            ),
                            [
                                'period' => $period,
                                'exception' => $e
                            ]
                        );
                    } else {
                        $this->debug(
                            sprintf(
                                'The opening hour of location id=%d for the day of \'%s\' couldn\'t be updated',
                                $this->getStoreKeeperId($dotObject),
                                $openDays[$openDay]
                            ),
                            [
                                'period' => $period,
                                'exception' => $e
                            ]
                        );
                    }
                }

                unset($period, $openDay, $openingHour, $openingHourId);
            }
        }

        $toRemove = array_diff_key($openingHours, $openDays);
        $closedDays = array_intersect_key($this->getDaysOfWeek(), $toRemove);
        try {
            $wpdb->query(OpeningHourModel::prepareQuery($deleteQuery));

            $this->debug(
                sprintf(
                    'The opening hours of location id=%d have been successfully cleaned.',
                    $this->getStoreKeeperId($dotObject)
                ),
                [
                    'days_of_week' => $closedDays,
                    'opening_hours' => $toRemove
                ]
            );
        } catch (\Exception $e) {
            $this->debug(
                sprintf(
                    'The opening hours of location id=%d couldn\'t be cleaned.',
                    $this->getStoreKeeperId($dotObject),
                ),
                [
                    'days_of_week' => $closedDays,
                    'opening_hours' => $toRemove,
                    'exception' => $e
                ]
            );
        }

        unset($deleteQuery, $openDays, $openingHours, $closedDays, $toRemove);

        return $this;
    }

    /**
     * Process location special opening hours
     *
     * @param Dot $dotObject
     * @param int $locationId
     * @return LocationImport
     */
    protected function processLocationSpecialOpeningHours(Dot $dotObject, int $locationId)
    {
        global $wpdb;

        $this->debug('Processing location special opening hours', $dotObject->get('opening_special_hour'));

        $locationStoreKeeperId = $this->getStoreKeeperId($dotObject);
        $openingSpecialHours = $dotObject->get('opening_special_hours', []);

        $deleteQuery = OpeningSpecialHoursModel::getDeleteHelper()
            ->where('location_id = :location_id')
            ->bindValue('location_id', $locationId);

        $openingSpecialHours = array_combine(array_column($openingSpecialHours, 'id'), $openingSpecialHours);
        $locationSpecialOpeningHoursIdMap = $this->getLocationSpecialOpeningHoursIdMap($locationId);

        foreach ($openingSpecialHours as $storeKeeperId => $openingSpecialHourData) {
            $openingSpecialHour = [
                'storekeeper_id' => (int) $openingSpecialHourData['id'],
                'location_id' => $locationId,
                'date' => $openingSpecialHourData['date'],
                'is_open' => (bool) $openingSpecialHourData['is_open'],
                'name' => isset($openingSpecialHourData['name']) && '' !== trim($openingSpecialHourData['name'])
                    ? $openingSpecialHourData['name']
                    : null
            ];

            $period = [
                'open_time' => null,
                'close_time' => null
            ];

            if (array_key_exists('periods', $openingSpecialHourData)) {
                $period = array_merge($period, array_intersect_key(reset($openingSpecialHourData['periods']), $period));
            }

            $openingSpecialHour = array_merge($openingSpecialHour, $period);

            if (array_key_exists($storeKeeperId, $locationSpecialOpeningHoursIdMap)) {
                try {
                    OpeningSpecialHoursModel::update(
                        $locationSpecialOpeningHoursIdMap[$storeKeeperId],
                        $openingSpecialHour
                    );

                    $this->debug(
                        sprintf(
                            'The special opening hour id=%d of location id=%d has been successfully updated.',
                            $storeKeeperId,
                            $locationStoreKeeperId,
                        ),
                        [
                            'special_opening_hour' => $openingSpecialHour
                        ]
                    );
                } catch (\Exception $e) {
                    $this->debug(
                        sprintf(
                            'The special opening hour id=%d of location id=%d couldn\'t be updated.',
                            $storeKeeperId,
                            $locationStoreKeeperId,
                        ),
                        [
                            'special_opening_hour' => $openingSpecialHour,
                            'exception' => $e
                        ]
                    );
                }
            } else {
                try {
                    $locationSpecialOpeningHoursIdMap[$openingSpecialHourData['id']] = OpeningSpecialHoursModel::create(
                        $openingSpecialHour
                    );

                    $this->debug(
                        sprintf(
                            'The special opening hour id=%d of location id=%d has been successfully created.',
                            $storeKeeperId,
                            $locationStoreKeeperId,
                        ),
                        [
                            'special_opening_hour' => $openingSpecialHour
                        ]
                    );
                } catch (\Exception $e) {
                    $this->debug(
                        sprintf(
                            'The special opening hour id=%d of location id=%d couldn\'t be created.',
                            $storeKeeperId,
                            $locationStoreKeeperId,
                        ),
                        [
                            'special_opening_hour' => $openingSpecialHour,
                            'exception' => $e
                        ]
                    );
                }
            }

            unset($openingSpecialHourData, $period, $openingSpecialHour, $storeKeeperId);
        }

        if ($openingSpecialHours) {
            $deleteQuery->where(
                sprintf('storekeeper_id NOT IN(%s)', $this->prepareWhereInValues(array_keys($openingSpecialHours)))
            );
        }

        if($wpdb->query(OpeningSpecialHoursModel::prepareQuery($deleteQuery))) {
            $this->debug(
                sprintf(
                    'The old special opening hours of location id=%d were successfully removed.',
                    $locationStoreKeeperId
                )
            );
        }

        return $this;
    }

    /**
     * Process location shipping setting
     *
     * @param Dot $dotObject
     * @param int $locationId
     * @return LocationImport
     */
    protected function processLocationShippingSetting(Dot $dotObject, int $locationId)
    {
        global $wpdb;

        $this->debug('Processing location shipping settings', $dotObject->get('location_shipping_setting', []));

        $locationStoreKeeperId = $this->getStoreKeeperId($dotObject);

        $shippingSettingStoreKeeperId = $dotObject->get('location_shipping_setting.id');

        $deleteQuery = ShippingSettingModel::getDeleteHelper()
            ->where('location_id = :location_id')
            ->bindValues(['location_id' => $locationId]);

        if (is_numeric($shippingSettingStoreKeeperId)) {
            $deleteQuery
                ->where('storekeeper_id != :storekeeper_id')
                ->bindValues(['storekeeper_id' => (int) $shippingSettingStoreKeeperId]);

            $shippingSetting = ShippingSettingModel::getByLocationId($locationId, $shippingSettingStoreKeeperId);

            $data = array_merge(
                array_intersect_key(
                    $dotObject->get('location_shipping_setting', []),
                    array_diff_key(
                        array_fill_keys(array_keys(ShippingSettingModel::getFieldsWithRequired()), null),
                        [
                            ShippingSettingModel::PRIMARY_KEY => null,
                            ShippingSettingModel::FIELD_DATE_CREATED => null,
                            ShippingSettingModel::FIELD_DATE_UPDATED => null,
                            'location_id' => null
                        ]
                    )
                ),
                [
                    'location_id' => $locationId,
                    'storekeeper_id' => (int) $shippingSettingStoreKeeperId
                ]
            );

            ShippingSettingModel::upsert($data, $shippingSetting);

            if (null === $shippingSetting) {
                $this->debug(
                    sprintf(
                        'Shipping setting id=%d of location id=%s was successfully created',
                        $shippingSettingStoreKeeperId,
                        $locationStoreKeeperId
                    )
                );
            } else {
                $this->debug(
                    sprintf(
                        'Shipping setting id=%d of location id=%s was successfully updated',
                        $shippingSettingStoreKeeperId,
                        $locationStoreKeeperId
                    )
                );
            }
        }

        $wpdb->query(ShippingSettingModel::prepareQuery($deleteQuery));

        return $this;
    }

    /**
     * Get location opening hours mapped by day of week
     *
     * @param int $locationId
     * @param bool $reload
     * @return array
     */
    protected function getLocationOpeningHours(int $locationId, bool $reload = false)
    {
        if (!array_key_exists($locationId, $this->locationOpeningHours) || $reload) {
            $openingHours = OpeningHourModel::getByLocationId($locationId);

            $this->locationOpeningHours[$locationId] = array_combine(
                array_column(
                    $openingHours,
                    'open_day'
                ),
                $openingHours
            );

            unset($openingHours);
        }

        return $this->locationOpeningHours[$locationId];
    }

    /**
     * Get location special opening hours ID map (`storekeeper_id` -> `id`)
     *
     * @param int $locationId
     * @param bool $reload
     * @return array
     */
    protected function getLocationSpecialOpeningHoursIdMap(int $locationId, bool $reload = false)
    {
        global $wpdb;

        if (!array_key_exists($locationId, $this->locationSpecialOpeningHoursIdMap) || $reload) {
            $mapSelectQuery = OpeningSpecialHoursModel::getSelectHelper()
                ->cols([OpeningSpecialHoursModel::PRIMARY_KEY, 'storekeeper_id'])
                ->where('location_id = :location_id')
                ->bindValue('location_id', $locationId);

            $this->locationSpecialOpeningHoursIdMap[$locationId] = array_column(
                $wpdb->get_results(OpeningSpecialHoursModel::prepareQuery($mapSelectQuery)),
                OpeningSpecialHoursModel::PRIMARY_KEY,
                'storekeeper_id'
            );

            unset($mapSelectQuery);
        }

        return $this->locationSpecialOpeningHoursIdMap[$locationId];
    }

    /**
     * Get mapped days of week (numeric representation -> full textual representation)
     *
     * @return array
     */
    protected function getDaysOfWeek(): array
    {
        if (null === $this->daysOfWeek) {
            $oldLocale = setlocale(LC_TIME, '0');
            $dayStart = date('d', strtotime('next Monday'));
            $this->daysOfWeek = [];

            setlocale(LC_TIME, 'en_US');
            for ($i = 0; $i < 7; $i++) {
                $time = mktime(0, 0, 0, date('m'), $dayStart + $i, date('y'));

                $this->daysOfWeek[date('w', $time)] = strtolower(date('l', $time));
            }
            setlocale(LC_TIME, $oldLocale);
        }

        return $this->daysOfWeek;
    }

    /**
     * Prepare values for (NOT) IN in where DB query part
     *
     * @param array
     * @return string
     */
    protected function prepareWhereInValues(array $values)
    {
        global $wpdb;

        return implode(
            ', ',
            array_map(
                function ($value) use ($wpdb) {
                    if (is_numeric($value)) {
                        return $wpdb->prepare('%d', $value);
                    }

                    return $wpdb->prepare('%s', $value);
                },
                $values
            )
        );
    }

    protected function getImportEntityName(): string
    {
        return __('location', I18N::DOMAIN);
    }

    protected function afterRun(array $storeKeeperIds)
    {
        global $wpdb;

        if (null !== $this->storekeeper_id) {
            if (!in_array($this->storekeeper_id, $storeKeeperIds)) {
                $locationId = LocationModel::getLocationIdByStoreKeeperId($this->storekeeper_id);

                if ($locationId) {
                    LocationModel::delete($locationId);
                }
            }
        } else if (!$this->import_limit && !$this->import_start) {
            $deleteQuery = LocationModel::getDeleteHelper();

            if ($storeKeeperIds) {
                $deleteQuery->where(sprintf('storekeeper_id NOT IN(%s)', $this->prepareWhereInValues($storeKeeperIds)));
            }

            $wpdb->query(LocationModel::prepareQuery($deleteQuery));
        }

        if (wp_cache_supports('flush_group')) {
            wp_cache_flush_group(STOREKEEPER_WOOCOMMERCE_B2C_NAME . LocationShippingCache::getCacheGroup());
        } else {
            wp_cache_flush();
        }

        parent::afterRun($storeKeeperIds);
    }
}
