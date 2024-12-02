<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceShopInfo;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\AddressModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningHourModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningSpecialHoursModel;

class SyncWoocommerceShopInfoTest extends AbstractTest
{
    public function testRun()
    {
        $this->initApiConnection();

        $this->mockApiCallsFromDirectory('commands/shop-info', false);

        $this->runner->execute(SyncWoocommerceShopInfo::getCommandName());

        $this->assertEquals('Goor', get_option('woocommerce_store_city'));

        $dumpData = $this
            ->getDataDump('commands/shop-info/moduleFunction.ShopModule::listLocationsForHook.success.json')
            ->getReturn()['data'];

        $this->assertSame(count($dumpData), LocationModel::count());

        foreach ($dumpData as $locationData) {
            $location = LocationModel::getByStoreKeeperId($locationData['id']);
            $this->assertIsArray($location);

            $locationAddress = AddressModel::getByLocationId($location[LocationModel::PRIMARY_KEY]);
            $this->assertIsArray($locationAddress);
            $this->assertEquals($locationData['address']['id'], $locationAddress['storekeeper_id']);

            $this->assertSame(
                !array_key_exists('opening_hour', $locationData)
                    ? 0
                    : count($locationData['opening_hour']['regular_periods']),
                    count(OpeningHourModel::getByLocationId($location[LocationModel::PRIMARY_KEY]))
            );
            $this->assertSame(
                array_key_exists('opening_special_hours', $locationData)
                    ? count($locationData['opening_special_hours'])
                    : 0,
                count(OpeningSpecialHoursModel::getByLocationId($location[LocationModel::PRIMARY_KEY]))
            );

            unset($locationData, $location, $locationAddress);
        }

        unset($dumpData);
    }
}
