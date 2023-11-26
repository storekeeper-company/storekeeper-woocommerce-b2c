<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceShippingMethods;

class SyncWoocommerceShippingMethodsTest extends AbstractTest
{
    const DATADUMP_DIRECTORY = 'commands/sync-woocommerce-shipping-methods';
    const DATADUMP_SOURCE_FILE = 'moduleFunction.ShopModule.listShippingMethodsForHooks.success.json';

    public function testRun()
    {
        $this->initApiConnection();
        $this->mockApiCallsFromDirectory(self::DATADUMP_DIRECTORY);


        // Read the original data from the data dump
        $file = $this->getDataDump(self::DATADUMP_DIRECTORY.'/'.self::DATADUMP_SOURCE_FILE);
        $originalShippingMethodsData = $file->getReturn()['data'];

        $expectedShippingMethodsPerCountry = [
            'NL' => [
                'JNT',
                'NinjaVan'
            ],
            'DE' => [
                'JNT'
            ],
            'PH' => [
                'NinjaVan'
            ]
        ];

        $this->runner->execute(SyncWoocommerceShippingMethods::getCommandName());

        $woocommerceShippingZones = \WC_Shipping_Zones::get_zones();
        $actualCountries = [];
        $actualShippingMethodsPerCountry = [];
        foreach ($woocommerceShippingZones as $shippingZone) {
            $woocommerceShippingZone = new \WC_Shipping_Zone($shippingZone['ID']);
            $isFromStoreKeeper = $woocommerceShippingZone->meta_exists('storekeeper_id');
            if ($isFromStoreKeeper) {
                $this->assertCount(1, $woocommerceShippingZone->get_zone_locations());
                $zoneName = $woocommerceShippingZone->get_zone_name();
                $actualCountries[] = $zoneName;
                $shippingMethods = $woocommerceShippingZone->get_shipping_methods();
                $actualShippingMethodsPerCountry[$zoneName] = [];
                foreach ($shippingMethods as $shippingMethod) {
                    $actualShippingMethodsPerCountry[$zoneName][] = $shippingMethod['name'];
                }
            }
        }

        $this->assertEquals(array_keys($expectedShippingMethodsPerCountry), $actualCountries, 'Shipping zones should match expected values');
        $this->assertEquals($expectedShippingMethodsPerCountry, $actualShippingMethodsPerCountry, 'Shipping methods does not match');
    }
}