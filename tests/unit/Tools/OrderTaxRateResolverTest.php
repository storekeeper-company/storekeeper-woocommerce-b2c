<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Tools;

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\OrderTaxRateResolver;
use StoreKeeper\WooCommerce\B2C\UnitTest\AbstractTest;

class OrderTaxRateResolverTest extends AbstractTest
{
    public function setUp(): void
    {
        parent::setUp();
        update_option('woocommerce_default_country', 'NL');
        StoreKeeperOptions::delete(StoreKeeperOptions::TAX_RATE_ID_MAP);
    }

    /**
     * A cached (country, value) pair must be served from the option without any
     * API call, because StoreKeeper TaxRate ids never change.
     */
    public function testFindTaxRateIdReturnsCachedIdWithoutApiCall(): void
    {
        StoreKeeperOptions::set(StoreKeeperOptions::TAX_RATE_ID_MAP, ['DE|19.0000' => 555]);

        $api = new class {
            public function getModule($name)
            {
                throw new \RuntimeException('API must not be called when the id is cached');
            }
        };

        $resolver = new OrderTaxRateResolver($api);

        $this->assertSame(555, $resolver->findTaxRateId('DE', 19.0), 'Cached id should be returned');
        // Different notations of the same percentage collapse to the same key.
        $this->assertSame(555, $resolver->findTaxRateId('de', 19), 'Normalised key should still hit the cache');
    }

    public function testFindTaxRateIdFetchesAndPersistsOnMiss(): void
    {
        $api = $this->makeApi([
            'data' => [
                ['id' => 77, 'name' => 'VAT 19% DE', 'alias' => null, 'value' => 19, 'country_iso2' => 'DE'],
            ],
            'total' => 1,
            'count' => 1,
        ]);

        $resolver = new OrderTaxRateResolver($api);

        $this->assertSame(77, $resolver->findTaxRateId('DE', 19.0), 'Should resolve via backoffice on a miss');
        $this->assertSame(1, $api->calls, 'Backoffice should be queried exactly once');

        $stored = StoreKeeperOptions::get(StoreKeeperOptions::TAX_RATE_ID_MAP, []);
        $this->assertSame(77, $stored['DE|19.0000'] ?? null, 'Resolved id should be persisted');

        // A second lookup is served from cache (still one API call total).
        $this->assertSame(77, $resolver->findTaxRateId('DE', 19.0));
        $this->assertSame(1, $api->calls, 'Second lookup must not hit the API again');
    }

    public function testFindTaxRateIdDoesNotCacheMiss(): void
    {
        $api = $this->makeApi([
            'data' => [],
            'total' => 0,
            'count' => 0,
        ]);

        $resolver = new OrderTaxRateResolver($api);

        $this->assertNull($resolver->findTaxRateId('DE', 19.0), 'No matching backoffice rate => null');
        $this->assertArrayNotHasKey(
            'DE|19.0000',
            StoreKeeperOptions::get(StoreKeeperOptions::TAX_RATE_ID_MAP, []),
            'A miss must not be cached'
        );

        // Because the miss is uncached, a later call retries (self-heals once the rate exists).
        $resolver->findTaxRateId('DE', 19.0);
        $this->assertSame(2, $api->calls, 'Uncached miss should be retried on the next call');
    }

    public function testFindTaxRateIdReturnsNullWithoutApi(): void
    {
        $resolver = new OrderTaxRateResolver(null);
        $this->assertNull($resolver->findTaxRateId('DE', 19.0), 'No API connection => null, no crash');
    }

    public function testResolveForItemResolvesForeignRate(): void
    {
        $rateId = \WC_Tax::_insert_tax_rate([
            'tax_rate_country' => 'DE',
            'tax_rate_state' => '',
            'tax_rate' => '19.0000',
            'tax_rate_name' => 'VAT 19 % DE',
            'tax_rate_priority' => '1',
            'tax_rate_compound' => '0',
            'tax_rate_shipping' => '1',
            'tax_rate_order' => '1',
        ]);

        // Warm cache so no API is required.
        StoreKeeperOptions::set(StoreKeeperOptions::TAX_RATE_ID_MAP, ['DE|19.0000' => 555]);

        $item = new \WC_Order_Item_Shipping();
        $item->set_taxes(['total' => [$rateId => '1.90']]);

        $resolver = new OrderTaxRateResolver(null);
        $this->assertSame(555, $resolver->resolveForItem($item), 'Foreign rate line should resolve to backoffice id');
    }

    public function testResolveForItemReturnsNullForBaseCountry(): void
    {
        $rateId = \WC_Tax::_insert_tax_rate([
            'tax_rate_country' => 'NL',
            'tax_rate_state' => '',
            'tax_rate' => '21.0000',
            'tax_rate_name' => 'BTW 21 % NL',
            'tax_rate_priority' => '1',
            'tax_rate_compound' => '0',
            'tax_rate_shipping' => '1',
            'tax_rate_order' => '1',
        ]);

        // Even with a warm cache entry, base-country lines keep the backoffice default.
        StoreKeeperOptions::set(StoreKeeperOptions::TAX_RATE_ID_MAP, ['NL|21.0000' => 999]);

        $item = new \WC_Order_Item_Shipping();
        $item->set_taxes(['total' => [$rateId => '2.10']]);

        $resolver = new OrderTaxRateResolver(null);
        $this->assertNull($resolver->resolveForItem($item), 'Base-country line must not carry a tax_rate_id');
    }

    public function testResolveForItemReturnsNullWhenNoTaxes(): void
    {
        $item = new \WC_Order_Item_Shipping();
        $item->set_taxes(['total' => []]);

        $resolver = new OrderTaxRateResolver(null);
        $this->assertNull($resolver->resolveForItem($item), 'Line without taxes => null');
    }

    public function testResolveForItemSkipsCompoundRates(): void
    {
        $rateA = \WC_Tax::_insert_tax_rate([
            'tax_rate_country' => 'DE',
            'tax_rate_state' => '',
            'tax_rate' => '19.0000',
            'tax_rate_name' => 'VAT 19 % DE',
            'tax_rate_priority' => '1',
            'tax_rate_compound' => '0',
            'tax_rate_shipping' => '1',
            'tax_rate_order' => '1',
        ]);
        $rateB = \WC_Tax::_insert_tax_rate([
            'tax_rate_country' => 'DE',
            'tax_rate_state' => '',
            'tax_rate' => '7.0000',
            'tax_rate_name' => 'VAT 7 % DE',
            'tax_rate_priority' => '2',
            'tax_rate_compound' => '1',
            'tax_rate_shipping' => '1',
            'tax_rate_order' => '2',
        ]);

        $item = new \WC_Order_Item_Shipping();
        $item->set_taxes(['total' => [$rateA => '1.90', $rateB => '0.70']]);

        $resolver = new OrderTaxRateResolver(null);
        $this->assertNull($resolver->resolveForItem($item), 'Ambiguous compound taxes must be skipped');
    }

    private function makeApi(array $listTaxRatesReturn)
    {
        return new class($listTaxRatesReturn) {
            public int $calls = 0;
            private array $ret;

            public function __construct(array $ret)
            {
                $this->ret = $ret;
            }

            public function getModule($name)
            {
                return $this;
            }

            public function listTaxRates($start, $limit, $order, $filters)
            {
                ++$this->calls;

                return $this->ret;
            }
        };
    }
}
