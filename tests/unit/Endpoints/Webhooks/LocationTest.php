<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\Webhooks;

use StoreKeeper\WooCommerce\B2C\Commands\ProcessAllTasks;
use StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints\AbstractTest;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\AddressModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningHourModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningSpecialHoursModel;
use StoreKeeper\WooCommerce\B2C\Imports\LocationUpdateImport;

class LocationTest extends AbstractTest
{

    /**#@+
     * Location hook dump files
     */
    public const ACTIVATE_LOCATION_DUMP_FILE = 'hook.events.activateLocation.json';
    public const DEACTIVATE_LOCATION_DUMP_FILE = 'hook.events.deactivateLocation.json';
    public const UPDATE_LOCATION_DUMP_FILE = 'hook.events.updateLocation.json';
    /**#@-*/

    /**
     * Scoped update location hook dump files
     */
    public const SCOPED_UPDATE_LOCATION_DUMP_FILES = [
        LocationUpdateImport::ADDRESS_SCOPE => 'hook.events.updateAddressLocation.json',
        LocationUpdateImport::OPENING_HOUR_SCOPE => 'hook.events.updateOpeningHourLocation.json',
        LocationUpdateImport::OPENING_SPECIAL_HOUR_SCOPE => 'hook.events.updateOpeningSpecialHourLocation.json'
    ];

    public function testLocationUpdated()
    {
        $this->initApiConnection();

        $storeKeeperId = $this->activateLocation(); //create location

        $location = LocationModel::getByStoreKeeperId($storeKeeperId);

        $this->assertIsArray($location);

        $address = AddressModel::getByLocationId($location[LocationModel::PRIMARY_KEY]);
        $openingHours = OpeningHourModel::getByLocationId($location[LocationModel::PRIMARY_KEY]);
        $openingSpecialHours = OpeningSpecialHoursModel::getByLocationId($location[LocationModel::PRIMARY_KEY]);

        foreach (array_merge([null], array_keys(self::SCOPED_UPDATE_LOCATION_DUMP_FILES)) as $scope) {
            sleep(1);

            $this->updateLocation($scope);

            $updatedLocation = LocationModel::getByStoreKeeperId($storeKeeperId);

            $this->assertIsArray($updatedLocation);

            $updatedAddress = AddressModel::getByLocationId($updatedLocation[LocationModel::PRIMARY_KEY]);
            $updatedOpeningHours = OpeningHourModel::getByLocationId($updatedLocation[LocationModel::PRIMARY_KEY]);
            $updatedOpeningSpecialHours = OpeningSpecialHoursModel::getByLocationId(
                $updatedLocation[LocationModel::PRIMARY_KEY]
            );

            $isLocationUpdated = $this->isEntityUpdated($location, $updatedLocation);
            $isAddressUpdated = $this->isEntityUpdated($address, $updatedAddress, AddressModel::FIELD_DATE_UPDATED);
            $areOpeningHoursUpdated = $this->areLocationOpeningHoursUpdated($openingHours, $updatedOpeningHours);
            $areOpeningSpecialHoursUpdated = $this->areLocationOpeningHoursUpdated(
                $openingSpecialHours,
                $updatedOpeningSpecialHours,
                OpeningSpecialHoursModel::class
            );

            if (null === $scope) {
                $this->assertTrue($isLocationUpdated);
                $this->assertFalse($isAddressUpdated);
                $this->assertFalse($areOpeningHoursUpdated);
                $this->assertFalse($areOpeningSpecialHoursUpdated);
            } else if (LocationUpdateImport::ADDRESS_SCOPE === $scope) {
                $this->assertFalse($isLocationUpdated);
                $this->assertTrue($isAddressUpdated);
                $this->assertFalse($areOpeningHoursUpdated);
                $this->assertFalse($areOpeningSpecialHoursUpdated);
            } else if (LocationUpdateImport::OPENING_HOUR_SCOPE === $scope) {
                $this->assertFalse($isLocationUpdated);
                $this->assertFalse($isAddressUpdated);
                $this->assertTrue($areOpeningHoursUpdated);
                $this->assertFalse($areOpeningSpecialHoursUpdated);
            } else if (LocationUpdateImport::OPENING_SPECIAL_HOUR_SCOPE === $scope) {
                $this->assertFalse($isLocationUpdated);
                $this->assertFalse($isAddressUpdated);
                $this->assertFalse($areOpeningHoursUpdated);
                $this->assertTrue($areOpeningSpecialHoursUpdated);
            }

            $location = $updatedLocation;
            $address = $updatedAddress;
            $openingHours = $updatedOpeningHours;
            $openingSpecialHours = $updatedOpeningSpecialHours;

            unset(
                $updatedLocation,
                $scope,
                $updatedLocation,
                $updatedAddress,
                $updatedOpeningHours,
                $updatedOpeningSpecialHours,
                $isLocationUpdated,
                $isAddressUpdated,
                $areOpeningHoursUpdated,
                $areOpeningSpecialHoursUpdated
            );
        }
    }
}
