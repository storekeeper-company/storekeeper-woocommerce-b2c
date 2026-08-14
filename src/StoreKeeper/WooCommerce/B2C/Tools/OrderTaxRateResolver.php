<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Exceptions\ExportException;
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
     * Returns null when the line carries no tax at all or when the rate maps to
     * the shop base country (the backoffice default already applies). Throws when
     * a foreign, taxed line cannot be mapped to a single StoreKeeper TaxRate,
     * because exporting it would otherwise produce an invoice with the wrong VAT.
     *
     * @throws ExportException when the line has ambiguous (compound) tax rates,
     *                         or a foreign rate with no matching StoreKeeper TaxRate
     */
    public function resolveForItem(\WC_Order_Item $item): ?int
    {
        $wcRate = $this->getWcRateForItem($item, true);
        if (null === $wcRate) {
            return null;
        }

        // Base-country lines keep the backoffice default; only foreign rates need translation.
        if ($wcRate['country'] === $this->getBaseCountry()) {
            return null;
        }

        $id = $this->findTaxRateId($wcRate['country'], $wcRate['percent']);
        if (null === $id) {
            throw new ExportException(sprintf('No StoreKeeper TaxRate found for country %1$s at %2$s%% (WooCommerce rate on order line "%3$s"). This tax rate is managed by StoreKeeper and cannot be configured here - please contact StoreKeeper support to have it added.', $wcRate['country'], self::formatPercent($wcRate['percent']), $this->describeItem($item)));
        }

        return $id;
    }

    /**
     * Resolve the StoreKeeper tax_rate_id to bucket this line under when the order
     * declares its own amounts.
     *
     * Unlike {@see resolveForItem} this also resolves base-country lines. A
     * declaring payload has to name a tax_rate_id on every line, because the
     * per-rate VAT it declares is keyed by the id the backoffice books the line
     * under - leaving a domestic line to fall back to the product's configured
     * rate would put it in a bucket that was never declared, which the backoffice
     * rejects.
     *
     * Never throws: any rate we cannot establish means "this order cannot declare
     * its amounts", which the caller handles by exporting the old way.
     */
    public function resolveBucketRateId(\WC_Order_Item $item): ?int
    {
        $wcRate = $this->getWcRateForItem($item, false);
        if (null === $wcRate) {
            return null;
        }

        return $this->findTaxRateId($wcRate['country'], $wcRate['percent']);
    }

    /**
     * The WooCommerce tax rate (country + percentage) a single order line carries.
     *
     * @param bool $throwOnCompound throw instead of returning null when the line
     *                              has more than one non-zero rate applied
     *
     * @return array{country: string, percent: float}|null
     *
     * @throws ExportException
     */
    private function getWcRateForItem(\WC_Order_Item $item, bool $throwOnCompound): ?array
    {
        // When WooCommerce tax calculation is disabled there is no destination
        // rate to preserve (and any leftover tax rows are stale), so never
        // resolve or fail on them.
        if (!function_exists('wc_tax_enabled') || !wc_tax_enabled()) {
            return null;
        }

        if (!is_callable([$item, 'get_taxes'])) {
            return null;
        }

        $taxes = $item->get_taxes();
        $totals = isset($taxes['total']) && is_array($taxes['total']) ? $taxes['total'] : [];

        if (empty($totals)) {
            // Line carries no tax at all - nothing to resolve.
            return null;
        }

        $appliedRateIds = [];
        foreach ($totals as $rateId => $amount) {
            if ('' !== $amount && null !== $amount && 0 != (float) $amount) {
                $appliedRateIds[] = (int) $rateId;
            }
        }

        // More than one non-zero rate on a single line (compound tax) cannot be
        // mapped to a single StoreKeeper tax_rate_id.
        if (count($appliedRateIds) > 1) {
            if ($throwOnCompound) {
                throw new ExportException(sprintf('Cannot resolve StoreKeeper tax_rate_id: order line "%s" has multiple (compound) tax rates applied.', $this->describeItem($item)));
            }

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

        $country = strtoupper((string) $wcRate['tax_rate_country']);
        if ('' === $country) {
            return null;
        }

        return [
            'country' => $country,
            'percent' => (float) ($wcRate['tax_rate'] ?? 0),
        ];
    }

    private function describeItem(\WC_Order_Item $item): string
    {
        if (is_callable([$item, 'get_name'])) {
            $name = (string) $item->get_name();
            if ('' !== $name) {
                return $name;
            }
        }

        return '#'.$item->get_id();
    }

    private static function formatPercent(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 4, '.', ''), '0'), '.');
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

        // StoreKeeper stores the tax rate as a fraction (e.g. 0.21) while
        // WooCommerce stores it as a percentage (e.g. 21). Convert before both
        // filtering and matching.
        $value = $percent / 100;

        $response = $this->storekeeperApi->getModule('ProductsModule')->listTaxRates(
            0,
            1,
            null,
            [
                [
                    'name' => 'country_iso2__=',
                    'val' => $countryIso2,
                ],
                [
                    'name' => 'value__=',
                    'val' => self::formatValue($value),
                ],
            ]
        );

        $row = $response['data'][0] ?? null;
        if (is_array($row) && isset($row['value'], $row['id']) && abs((float) $row['value'] - $value) < 0.0001) {
            return (int) $row['id'];
        }

        return 0;
    }

    private static function formatValue(float $value): string
    {
        // Round first to shed floating-point noise from the /100 conversion
        // (e.g. 0.21000000000000002) before formatting/trimming.
        return rtrim(rtrim(number_format(round($value, 4), 4, '.', ''), '0'), '.');
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
