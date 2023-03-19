<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations;

use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V20230312154000webhook;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V20230312154010task;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V20230312154020attribute;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V20230312154030attributeOption;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V20230312154040Payment;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V20230312154050Refund;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V20230313161100RedirectTable;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V20230313161110SetUpOptions;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V20230313171650AttributeFkEnsure;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V20230313171651AttributeOptionFkAttributeEnsure;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V20230313171652AttributeOptionFkTermEnsure;

class AllVersions implements VersionsInterface
{
    const VERSION_REGEX = '/\\V(?<date>\d{8})(?<time>\d{6})(\D\w*)$/';
    const VERSION = [
        V20230312154000webhook::class,
        V20230312154010task::class,
        V20230312154020attribute::class,
        V20230312154030attributeOption::class,
        V20230312154040Payment::class,
        V20230312154050Refund::class,
        V20230313161100RedirectTable::class,
        V20230313161110SetUpOptions::class,
        V20230313171650AttributeFkEnsure::class,
        V20230313171651AttributeOptionFkAttributeEnsure::class,
        V20230313171652AttributeOptionFkTermEnsure::class,
    ];

    public function getVersionId(string $class): int
    {
        if (!preg_match(self::VERSION_REGEX, $class, $matches)) {
            throw new BaseException('Wrong version class naming: '.$class);
        }

        return $matches['date'].$matches['time'];
    }

    public function getVersionsById(): array
    {
        $versions = [];
        foreach (self::VERSION as $class) {
            $versionId = self::getVersionId($class);
            if (array_key_exists($versionId, $versions)) {
                throw new \Exception("Version duplicate $versionId. Classes: ".implode(', ', [$class, $versions[$versionId]]));
            }
            $versions[$versionId] = $class;
        }

        return $versions;
    }
}