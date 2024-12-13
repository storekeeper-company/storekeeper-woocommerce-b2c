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

    /**
     * Testing location update task
     */
    /**
     * @dataProvider locationUpdateDataProvider
     */
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

    /**
     * Testing location status (activate/deactivate) task
     */
    public function testLocationStatus()
    {
        $this->initApiConnection();

        $storeKeeperId = $this->activateLocation();

        $location = LocationModel::getByStoreKeeperId($storeKeeperId);

        $this->assertIsArray($location, 'The location from dump response should be stored in database');
        $this->assertTrue(
            (bool) $location['is_active'],
            'The location status from dump response should match the one stored in database'
        );

        $this->deactivateLocation();

        $location = LocationModel::getByStoreKeeperId($storeKeeperId);

        $this->assertFalse(
            (bool) $location['is_active'],
            'The location status from dump response should match the one stored in database'
        );

        $this->activateLocation();

        $location = LocationModel::getByStoreKeeperId($storeKeeperId);

        $this->assertTrue(
            (bool) $location['is_active'],
            'The location status from dump response should match the one stored in database'
        );
    }

    /**
     * Update location
     *
     * @param null|string $scope
     * @return int
     */
    protected function updateLocation($scope = null)
    {
        if (null === $scope) {
            $dumpFileName = self::UPDATE_LOCATION_DUMP_FILE;
        } else {
            $dumpFileName = self::SCOPED_UPDATE_LOCATION_DUMP_FILES[$scope];
        }

        return $this->runAndExecuteHook($dumpFileName, TaskHandler::LOCATION_UPDATE);
    }

    /**
     * Activate location
     *
     * @return int
     */
    protected function activateLocation()
    {
        return $this->runAndExecuteHook(self::ACTIVATE_LOCATION_DUMP_FILE, TaskHandler::LOCATION_ACTIVATED);
    }

    /**
     * Deactivate location
     *
     * @return int
     */
    protected function deactivateLocation()
    {
        return $this->runAndExecuteHook(self::DEACTIVATE_LOCATION_DUMP_FILE, TaskHandler::LOCATION_DEACTIVATED);
    }

    /**
     * Testing location tasks
     *
     * @param string $dumpFileName
     * @param string $expectedType
     * @return void
     */
    protected function runAndExecuteHook($dumpFileName, $expectedType): int
    {
        $dumpFilePath = $this->getDumpFilePath($dumpFileName);
        $hookFile = $this->getHookDataDump($dumpFilePath . DIRECTORY_SEPARATOR . $dumpFileName);
        $backrefData = StoreKeeperApi::extractMainTypeAndOptions($hookFile->getEventBackref());

        $rest = $this->getRestWithToken($hookFile);
        $response = $this->handleRequest($rest);
        $response = $response->get_data();

        $backrefData = StoreKeeperApi::extractMainTypeAndOptions($hookFile->getEventBackref());

        $this->assertTrue($response['success'], 'Hook call successfull');

        if (count($hookFile->getBody()['payload']['events'])) {
            $taskIds = TaskModel::getTasksByStoreKeeperId((int) $backrefData[1]['id']);

            $this->assertNotNull(
                $taskIds,
                'The task IDs should not be null. This indicates that tasks were not found in the database for the given StoreKeeper ID.'
            );

            $this->assertSameSize(
                $hookFile->getBody()['payload']['events'],
                $taskIds,
                'The number of events from the payload should match the number of tasks retrieved from the database by storekeeper ID.'
            );

            foreach ($taskIds as $taskId) {
                $task = TaskModel::get($taskId);

                $this->assertSame(
                    $expectedType,
                    $task['type'],
                    sprintf(
                        'The task type should match the expected type. Expected: %s, Actual: %s, Task ID: %d',
                        $expectedType,
                        $task['type'],
                        $taskId
                    )
                );


                unset($taskId, $task);
            }
        }

        $this->mockApiCallsFromDirectory($dumpFilePath . DIRECTORY_SEPARATOR . 'dump', true);
        $this->runner->execute(ProcessAllTasks::getCommandName());

        return (int) $backrefData[1]['id'];
    }

    /**
     * Resolve dump file path
     *
     * @param string $dumpFileName
     * @return string
     */
    protected function getDumpFilePath($dumpFileName)
    {
        $matches = [];

        preg_match('/^hook\.([^\.]+)\.([^\.]+)Location\.json$/', $dumpFileName, $matches);

        return $matches[1] . DIRECTORY_SEPARATOR . 'location' . DIRECTORY_SEPARATOR . $matches[2];
    }

    /**
     * Check by update date field whether the location entity was updated
     *
     * @param null|array $entityA
     * @param null|array $entityB
     * @param string $dateField
     * @return bool
     */
    protected function isEntityUpdated($entityA, $entityB, $dateField = LocationModel::FIELD_DATE_UPDATED)
    {
        if (gettype($entityA) !== gettype($entityB)) {
            return true;
        }

        if (null === $entityA) {
            return false;
        }

        return 0 !== strcmp($entityA[$dateField], $entityB[$dateField]);
    }

    /**
     * Check whether opening (special) hours were updated
     *
     * @param array $openingHoursA
     * @param array $openingHoursB
     * @param string $entityModel
     * @return bool
     */
    protected function areLocationOpeningHoursUpdated(
        array $openingHoursA,
        array $openingHoursB,
        $entityModel = OpeningHourModel::class
    ) {
        if (count($openingHoursA) !== count($openingHoursB)) {
            return true;
        }

        $openingHoursA = $this->mapLocationOpeningHours($openingHoursA, $entityModel);
        $openingHoursB = $this->mapLocationOpeningHours($openingHoursB, $entityModel);

        if (array_diff_key($openingHoursA, $openingHoursB) || array_diff_key($openingHoursB, $openingHoursA)) {
            return true;
        }

        foreach ($openingHoursA as $openingHourIdA => $openingHourA) {
            if ($openingHoursB[$openingHourIdA] !== $openingHourA) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map opening (special) hours
     *
     * @param array $openingHours
     * @param string $entityModel
     * @return array
     */
    protected function mapLocationOpeningHours(array $openingHours, $entityModel = OpeningHourModel::class)
    {
        $openingHours = array_reduce(
            $openingHours,
            function (array $carry, array $openingHour) use ($entityModel) {
                $carry[$openingHour[constant("$entityModel::PRIMARY_KEY")]] = $openingHour[
                    constant("$entityModel::FIELD_DATE_UPDATED")
                ];

                return $carry;
            },
            []
        );

        ksort($openingHours, SORT_NUMERIC);

        return $openingHours;
    }
}
