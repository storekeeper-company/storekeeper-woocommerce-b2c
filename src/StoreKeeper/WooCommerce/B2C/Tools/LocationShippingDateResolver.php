<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Models\LocationModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningHourModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningSpecialHoursModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\ShippingSettingModel;
use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;
use StoreKeeper\WooCommerce\B2C\Cache\LocationShippingCache;

class LocationShippingDateResolver
{

    /**#@+
     * Delivery types
     */
    protected const PICKUP_TYPE = 'pickup';
    protected const TRUCK_DELIVERY_TYPE = 'truck_delivery';
    /**#@-*/

    /**#@+
     * Cache keys
     */
    protected const OPENING_HOURS_CACHE_KEY = 'opening_hours';
    protected const OPENING_SPECIAL_HOURS_CACHE_KEY = 'opening_special_hours';
    protected const SHIPPING_SETTING_CACHE_KEY = 'shipping_setting';
    /**#@-*/

    /**
     * Location opening hours information
     *
     * @var null|array
     */
    protected static $openingHoursInfo;

    /**
     * Location opening special hours information
     *
     * @var null|array
     */
    protected static $openingSpecialHoursInfo;

    /**
     * Location shipping setting information
     *
     * @var null|array
     */
    protected static $shippingSettingInfo;

    /**
     * Resolve pickup date
     *
     * @param mixed $location
     * @param null|\DateTime $date
     * @return null|\DateTimeImmutable
     */
    public static function getPickupDate($location, ?\DateTime $date = null): ?\DateTimeImmutable
    {
        return self::getDeliveryDateTime($location, self::PICKUP_TYPE, $date);
    }

    /**
     * Resolve truck delivery date
     *
     * @param mixed $location
     * @param null|\DateTime $date
     * @return null|\DateTimeImmutable
     */
    public static function getTruckDeliveryDate($location, ?\DateTime $date = null): ?\DateTimeImmutable
    {
        return self::getDeliveryDateTime($location, self::TRUCK_DELIVERY_TYPE, $date);
    }

    /**
     * Resolve delivery date
     *
     * @param mixed $location
     * @param string $type
     * @param null|\DateTime $date
     * @return null|\DateTimeImmutable
     */
    protected static function getDeliveryDateTime($location, $type, ?\DateTime $date = null): ?\DateTimeImmutable
    {
        if (null === $date) {
            $date = DateTimeHelper::currentDateTime();
        }

        $date->setTimezone(wp_timezone());

        try {
            $location = self::resolveLocation($location);
        } catch (\Exception $e) {
            return null;
        }

        $locationId = (int) $location[LocationModel::PRIMARY_KEY];
        $shippingSetting = self::getShippingSetting($locationId);
        if (!$shippingSetting || !$shippingSetting[sprintf('is_%s_next_day', $type)]) {
            return null;
        }

        $cutoffDateTime = self::getCutoffDateTime($shippingSetting[sprintf('%s_next_day_cutoff_time', $type)]);
        $deliveryDate = (clone $date)->modify(sprintf('+ %d days', $date <= $cutoffDateTime ? 1 : 2));
        $max = (clone $date)->modify('+1 year');
        $interval = $date->diff($max);

        $i = 0;
        while (null === ($openingTime = self::getLocationOpeningTime($locationId, $deliveryDate)) &&
            $i < $interval->days) {
            $deliveryDate->modify('+1 day');
            $i++;
        }

        if (null !== $openingTime) {
            $deliveryDate->setTime($openingTime['hour'], $openingTime['minute'], $openingTime['second']);

            return \DateTimeImmutable::createFromMutable($deliveryDate);
        }

        return null;
    }

    /**
     * Get cut-off time
     *
     * @param string $cutoffTime
     * @return \DateTime
     */
    protected static function getCutoffDateTime(string $cutoffTime): \DateTime
    {
        $time = self::parseTime($cutoffTime);

        return DateTimeHelper::currentDateTime()->setTimezone(wp_timezone())
            ->setTime($time['hour'], $time['minute'], $time['second']);
    }

    /**
     * Get all location opening hours
     *
     * @param int $locationId
     * @return null|array
     */
    protected static function getLocationOpeningTime($locationId, \DateTime $date)
    {
        $openingSpecialHourInfo = self::getLocationOpeningSpecialHourInfo($locationId, $date);
        if (null !== $openingSpecialHourInfo) {
            if (!$openingSpecialHourInfo['is_open']) {
                return null;
            }

            return self::parseTime($openingSpecialHourInfo['open_time']);
        }

        $openingHourInfo = self::getLocationOpeningHourInfo($locationId, $date);
        if ($openingHourInfo) {
            return self::parseTime($openingHourInfo);
        }

        return null;
    }

    /**
     * Get location opening special hour information for on a specific date
     *
     * @param int $locationId
     * @param \DateTime $date
     * @return null|array
     */
    protected static function getLocationOpeningSpecialHourInfo($locationId, \DateTime $date)
    {
        $openingSpecialHoursMap = self::getLocationOpeningSpecialHoursMap($locationId);
        $key = $date->format(DateTimeHelper::MYSQL_DATE_FORMAT);

        if (array_key_exists($key, $openingSpecialHoursMap)) {
            return $openingSpecialHoursMap[$key];
        }

        return null;
    }

    /**
     * Get location opening hour information on a specific date
     *
     * @param int $locationId
     * @param \DateTime $date
     * @return null|string
     */
    protected static function getLocationOpeningHourInfo($locationId, \DateTime $date)
    {
        $openingHoursMap = self::getLocationOpeningHoursMap($locationId);
        $key = (int) $date->format('w');

        if (array_key_exists($key, $openingHoursMap)) {
            return $openingHoursMap[$key];
        }

        return null;
    }

    /**
     * Get location shipping setting
     *
     * @param int $locationId
     * @return null|array
     */
    protected static function getShippingSetting($locationId): ?array
    {
        if (null === self::$shippingSettingInfo) {
            self::$shippingSettingInfo = [];

            if (LocationShippingCache::exists(self::SHIPPING_SETTING_CACHE_KEY)) {
                self::$shippingSettingInfo = LocationShippingCache::get(self::SHIPPING_SETTING_CACHE_KEY);
            }
        }

        if (!array_key_exists($locationId, self::$shippingSettingInfo)) {
            self::$shippingSettingInfo[$locationId] = ShippingSettingModel::getByLocationId($locationId);

            if (self::$shippingSettingInfo[$locationId]) {
                self::$shippingSettingInfo[$locationId] = array_intersect_key(
                    self::$shippingSettingInfo[$locationId],
                    array_fill_keys(
                        [
                            'is_pickup_next_day',
                            'is_truck_delivery_next_day',
                            'pickup_next_day_cutoff_time',
                            'truck_delivery_next_day_cutoff_time'
                        ],
                        null
                    )
                );
            }

            LocationShippingCache::set(self::SHIPPING_SETTING_CACHE_KEY, self::$shippingSettingInfo);
        }

        return self::$shippingSettingInfo[$locationId];
    }

    /**
     * Get location opening hours map
     *
     * @param int $locationId
     * @return array
     */
    protected static function getLocationOpeningHoursMap($locationId)
    {
        global $wpdb;

        if (null === self::$openingHoursInfo) {
            self::$openingHoursInfo = [];

            if (LocationShippingCache::exists(self::OPENING_HOURS_CACHE_KEY)) {
                self::$openingHoursInfo = LocationShippingCache::get(self::OPENING_HOURS_CACHE_KEY);
            }
        }

        if (!array_key_exists($locationId, self::$openingHoursInfo)) {
            $select = OpeningHourModel::getSelectHelper();
            $select
                ->cols(['open_day', 'open_time'])
                ->where('location_id = :location_id')
                ->bindValue('location_id', (int) $locationId)
                ->orderBy(['open_day ASC']);

            $query = OpeningHourModel::prepareQuery($select);

            self::$openingHoursInfo[$locationId] = array_column(
                $wpdb->get_results($query, ARRAY_A),
                'open_time',
                'open_day'
            );

            unset($select, $query);

            LocationShippingCache::set(self::OPENING_HOURS_CACHE_KEY, self::$openingHoursInfo);
        }

        return self::$openingHoursInfo[$locationId];
    }

    /**
     * Get all location opening special hours map
     *
     * @param int $locationId
     * @return array
     */
    protected static function getLocationOpeningSpecialHoursMap($locationId)
    {
        global $wpdb;

        if (null === self::$openingSpecialHoursInfo) {
            self::$openingSpecialHoursInfo = [];

            if (LocationShippingCache::exists(self::OPENING_SPECIAL_HOURS_CACHE_KEY)) {
                self::$openingSpecialHoursInfo = LocationShippingCache::get(self::OPENING_SPECIAL_HOURS_CACHE_KEY);
            }
        }

        if (!array_key_exists($locationId, self::$openingSpecialHoursInfo)) {
            self::$openingSpecialHoursInfo[$locationId] = [];

            $select = OpeningSpecialHoursModel::getSelectHelper();
            $select
                ->cols(['date', 'is_open', 'open_time'])
                ->where('location_id = :location_id')
                ->bindValue('location_id', (int) $locationId)
                ->orderBy(['date ASC']);

            $query = OpeningSpecialHoursModel::prepareQuery($select);

            foreach ($wpdb->get_results($query, ARRAY_A) as $info) {
                self::$openingSpecialHoursInfo[$locationId][$info['date']] = $info;

                unset(self::$openingSpecialHoursInfo[$locationId][$info['date']]['date'], $info);
            }

            unset($select, $query);

            LocationShippingCache::set(self::OPENING_SPECIAL_HOURS_CACHE_KEY, self::$openingSpecialHoursInfo);
        }

        return self::$openingSpecialHoursInfo[$locationId];
    }

    /**
     * Resolve location
     *
     * @param mixed $location
     * @return array
     * @throws \Exception
     */
    protected static function resolveLocation($location)
    {
        if (is_numeric($location)) {
            $location = LocationModel::get($location);
        }

        if (is_object($location) && ($location instanceof \stdClass || method_exists($location, '__toArray'))) {
            $location = (array) $location;
        }

        if (!is_array($location)) {
            throw new \Exception('Invalid location');
        }

        LocationModel::validateData($location);

        return $location;
    }

    /**
     * Parse time string
     *
     * @param string $timeStr
     * @return array
     */
    protected static function parseTime(string $timeStr): array
    {
        $time = [];

        preg_match('/^(?<hour>\d{2}):(?<minute>\d{2})(:(?<second>\d{2}))?$/', $timeStr, $time);

        $time = array_merge(['second' => 0], array_intersect_key($time, array_fill_keys(['hour', 'minute'], null)));

        return array_map('intval', $time);
    }
}
