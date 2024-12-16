<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Commands\SyncWoocommerceFullSync;
use StoreKeeper\WooCommerce\B2C\Imports\ProductImport;
use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;
use StoreKeeper\WooCommerce\B2C\Models\AttributeOptionModel;
use StoreKeeper\WooCommerce\B2C\Options\FeaturedAttributeOptions;
use StoreKeeper\WooCommerce\B2C\Tools\Attributes;
use StoreKeeper\WooCommerce\B2C\Tools\Categories;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use StoreKeeper\WooCommerce\B2C\Models\LocationModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\AddressModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningHourModel;
use StoreKeeper\WooCommerce\B2C\Models\Location\OpeningSpecialHoursModel;

class SyncWoocommerceFullSyncTest extends AbstractTest
{
    public const SYNC_DIR = 'commands/full-sync';

    public function testFull()
    {
        $this->initApiConnection();

        $this->mockApiCallsFromDirectory('commands/full-sync');
        $this->mockApiCallsFromCommonDirectory();
        $this->mockMediaFromDirectory('commands/full-sync/media');

        $this->runner->execute(SyncWoocommerceFullSync::getCommandName());

        // Shop info
        $shopWithRelationData = $this->getMergedDataDump('ShopModule::getShopWithRelation');
        $shopWithRelation = new Dot($shopWithRelationData);
        $this->assertEquals(
            $shopWithRelation->get('relation_data.contact_address.city'),
            get_option('woocommerce_store_city'),
            'WooCommerce city incorrect'
        );
        $this->assertEquals(
            $shopWithRelation->get('relation_data.contact_address.zipcode'),
            get_option('woocommerce_store_postcode'),
            'WooCommerce zipcode incorrect'
        );

        $locationDumpData = $this->getMergedDataDump('ShopModule::listLocationsForHook');
        $this->assertSame(count($locationDumpData), LocationModel::count());
        $this->assertSame(count($locationDumpData), AddressModel::count());
        $this->assertSame(
            array_reduce(
                $locationDumpData,
                function ($carry, $locationData) {
                    $openingHours = 0;
                    if (array_key_exists('opening_hour', $locationData)) {
                        $openingHours = count($locationData['opening_hour']['regular_periods']);
                    }

                    return $carry + $openingHours;
                },
                0
            ),
            OpeningHourModel::count()
        );
        $this->assertSame(
            array_reduce(
                $locationDumpData,
                function ($carry, $locationData) {
                    $openingSpecialHours = 0;
                    if (array_key_exists('opening_special_hours', $locationData)) {
                        $openingSpecialHours = count($locationData['opening_special_hours']);
                    }

                    return $carry + $openingSpecialHours;
                },
                0
            ),
            OpeningSpecialHoursModel::count()
        );
        unset($locationDumpData);

        // Shop config
        $shopConfigs = $this->getMergedDataDump('ShopModule::listConfigurations');
        $shopConfig = current($shopConfigs);
        $this->assertNotEmpty(
            $shopConfig,
            'Missing shop config'
        );
        $this->assertEquals(
            $shopConfig['currency_iso3'],
            get_option('woocommerce_currency'),
            'Shop currency incorrect'
        );

        // Categories & Tags
        $categoriesOrLabels = $this->getMergedDataDump('ShopModule::listTranslatedCategoryForHooks');
        foreach ($categoriesOrLabels as $categoryOrLabelData) {
            $categoryOrLabel = new Dot($categoryOrLabelData);
            $typeAlias = $categoryOrLabel->get('category_type.alias');
            $id = $categoryOrLabel->get('id');
            if ('Product' === $typeAlias) {
                $this->assertNotEmpty(
                    Categories::getCategoryById($id),
                    'Missing category'
                );
            } else {
                if ('Label' === $typeAlias) {
                    $labels = WordpressExceptionThrower::throwExceptionOnWpError(
                        get_terms(
                            [
                                'taxonomy' => 'product_tag',
                                'hide_empty' => false,
                                'number' => 1,
                                'meta_key' => 'storekeeper_id',
                                'meta_value' => $id,
                            ]
                        )
                    );
                    $this->assertCount(
                        1,
                        $labels,
                        'Missing tag/label'
                    );
                } else {
                    $this->assertTrue(
                        false,
                        "Unknown attribute type alias '$typeAlias'"
                    );
                }
            }
        }

        // Attributes
        $attributes = $this->getMergedDataDump('BlogModule::listTranslatedAttributes');
        foreach ($attributes as $attribute) {
            $this->assertNotEmpty(
                Attributes::getAttribute($attribute['id']),
                'Missing attribute: '.json_encode($attribute)
            );
        }

        // Features attributes
        $featuredAttributes = $this->getMergedDataDump('ProductsModule::listFeaturedAttributes');
        foreach ($featuredAttributes as $featuredAttribute) {
            $this->assertNotEmpty(
                FeaturedAttributeOptions::getWooCommerceAttributeName($featuredAttribute['alias']),
                'Missing featured attribute: '.json_encode($featuredAttribute)
            );
        }

        // Attribute options
        $attributeOptions = $this->getMergedDataDump('BlogModule::listTranslatedAttributeOptions');
        foreach ($attributeOptions as $attributeOptionData) {
            $attributeOption = new Dot($attributeOptionData);
            $attributeOptionId = $attributeOption->get('id');
            $attribute = AttributeModel::getAttributeByStoreKeeperId($attributeOption->get('attribute_id'));
            $termId = AttributeOptionModel::getTermIdByStorekeeperId(
                $attribute->id,
                $attributeOptionId
            );

            $this->assertNotEmpty(
                $termId,
                'Missing attribute option: '.json_encode($attributeOptionData)
            );
        }

        // Products
        $products = $this->getMergedDataDump('ShopModule::naturalSearchShopFlatProductForHooks');
        foreach ($products as $productData) {
            $product = new Dot($productData);
            $this->assertNotEmpty(
                ProductImport::getProductByProductData($product),
                'Missing product'
            );
        }
    }

    private function getMergedDataDump($partialFileName)
    {
        $directory = $this->getDataDir().self::SYNC_DIR;
        $files = array_diff(scandir($directory), ['..', '.']);
        $returnArray = [];

        foreach ($files as $file) {
            if (false !== strpos($file, $partialFileName)) {
                $dataDump = $this->getDataDump(self::SYNC_DIR.'/'.$file);
                $returnData = $dataDump->getReturn();

                if (array_key_exists('data', $returnData)) {
                    $returnArray = array_merge($returnArray, $returnData['data']);
                } else {
                    $returnArray = array_merge($returnArray, $returnData);
                }
            }
        }

        return $returnArray;
    }
}
