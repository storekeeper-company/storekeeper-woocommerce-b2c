<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

/**
 * Resolves the StoreKeeper backoffice tax_rate_id for a WooCommerce order line.
 *
 * Cross-border EU orders (e.g. handled by the "One Stop Shop for WooCommerce"
 * plugin) stamp a destination-country WooCommerce tax rate onto each order line.
 * The StoreKeeper backend cannot infer that destination rate from the product
 * alone, so on export we translate the WooCommerce rate the line carries
 * (country + percentage) into the matching StoreKeeper TaxRate id.
 *
 * StoreKeeper TaxRate ids never change, so every resolved (country, value) pair
 * is persisted in {@see StoreKeeperOptions::TAX_RATE_ID_MAP}. A cache hit costs a
 * single option read; a miss costs one filtered ProductsModule::listTaxRates call
 * and is then cached forever. Misses (no matching backoffice rate) are NOT cached,
 * so a rate added in the backoffice later is picked up on the next order.
 */
class OrderTaxRateResolver
{
    /**
     * @var mixed the StoreKeeper API wrapper (nullable so the resolver degrades
     *            gracefully when constructed without an API connection)
     */
    private $storekeeperApi;

    private ?array $map = null;

    private ?string $baseCountry = null;

    /**
     * @param mixed $storekeeperApi the StoreKeeper API wrapper, or null
     */
    public function __construct($storekeeperApi = null)
    {
        $this->storekeeperApi = $storekeeperApi;
    }

    /**
     * Resolve the StoreKeeper tax_rate_id for a WooCommerce order line item.
     *
     * Returns null when the line carries no usable tax rate, when the rate maps
     * to the shop base country (the backoffice default already applies), or when
     * no matching StoreKeeper TaxRate exists.
     */
    public function resolveForItem(\WC_Order_Item $item): ?int
    {
        $rate = $this->readWcRate($item);
        if (null === $rate) {
            return null;
        }

        [$country, $percent] = $rate;

        // Base-country lines keep the backoffice default; only foreign rates need translation.
        if ('' === $country || $country === $this->getBaseCountry()) {
            return null;
        }

        return $this->findTaxRateId($country, $percent);
    }

    /**
     * Read the WooCommerce tax rate the order line carries.
     *
     * @return array{0: string, 1: float}|null [country_iso2, percentage], or null
     */
    private function readWcRate(\WC_Order_Item $item): ?array
    {
        if (!is_callable([$item, 'get_taxes'])) {
            return null;
        }

        $taxes = $item->get_taxes();
        $totals = isset($taxes['total']) && is_array($taxes['total']) ? $taxes['total'] : [];

        if (empty($totals)) {
            return null;
        }

        $appliedRateIds = [];
        foreach ($totals as $rateId => $amount) {
            if ('' !== $amount && null !== $amount && 0 != (float) $amount) {
                $appliedRateIds[] = (int) $rateId;
            }
        }

        // More than one non-zero rate on a single line (compound tax) is ambiguous - skip.
        if (count($appliedRateIds) > 1) {
            return null;
        }

        if (1 === count($appliedRateIds)) {
            $rateId = $appliedRateIds[0];
        } else {
            // No tax charged but a rate row exists - use it (legitimate 0% rate).
            $rateId = (int) array_key_first($totals);
        }

        if ($rateId <= 0) {
            return null;
        }

        $wcRate = \WC_Tax::_get_tax_rate($rateId, ARRAY_A);
        if (empty($wcRate) || !isset($wcRate['tax_rate_country'])) {
            return null;
        }

        return [
            strtoupper((string) $wcRate['tax_rate_country']),
            (float) ($wcRate['tax_rate'] ?? 0),
        ];
    }

    /**
     * Look up the StoreKeeper TaxRate id for a country + percentage, cache-first.
     */
    public function findTaxRateId(string $countryIso2, float $percent): ?int
    {
        $countryIso2 = strtoupper($countryIso2);
        $key = self::cacheKey($countryIso2, $percent);

        $map = $this->getMap();
        if (array_key_exists($key, $map)) {
            $cached = (int) $map[$key];

            return $cached > 0 ? $cached : null;
        }

        $id = $this->fetchFromBackoffice($countryIso2, $percent);
        if ($id > 0) {
            // Persist only positive hits: a miss must stay uncached so a rate
            // that is added in the backoffice later is picked up next time.
            $map[$key] = $id;
            $this->map = $map;
            StoreKeeperOptions::set(StoreKeeperOptions::TAX_RATE_ID_MAP, $map);

            return $id;
        }

        return null;
    }

    private function fetchFromBackoffice(string $countryIso2, float $percent): int
    {
        if (null === $this->storekeeperApi) {
            return 0;
        }

        $response = $this->storekeeperApi->getModule('ProductsModule')->listTaxRates(
            0,
            100,
            null,
            [
                [
                    'name' => 'country_iso2__=',
                    'val' => $countryIso2,
                ],
            ]
        );

        $data = $response['data'] ?? [];
        foreach ($data as $row) {
            if (!isset($row['value'], $row['id'])) {
                continue;
            }

            if (abs((float) $row['value'] - $percent) < 0.0001) {
                return (int) $row['id'];
            }
        }

        return 0;
    }

    private function getMap(): array
    {
        if (null === $this->map) {
            $stored = StoreKeeperOptions::get(StoreKeeperOptions::TAX_RATE_ID_MAP, []);
            $this->map = is_array($stored) ? $stored : [];
        }

        return $this->map;
    }

    private static function cacheKey(string $countryIso2, float $percent): string
    {
        return $countryIso2.'|'.number_format($percent, 4, '.', '');
    }

    private function getBaseCountry(): string
    {
        if (null === $this->baseCountry) {
            $this->baseCountry = function_exists('WC') && WC()->countries
                ? strtoupper((string) WC()->countries->get_base_country())
                : '';
        }

        return $this->baseCountry;
    }
}
