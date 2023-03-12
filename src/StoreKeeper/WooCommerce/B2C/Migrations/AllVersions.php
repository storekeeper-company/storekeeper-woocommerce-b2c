<?php

namespace StoreKeeper\WooCommerce\B2C\Migrations;

use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V_20230312_154000_webhook;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V_20230312_154010_task;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V_20230312_154020_attribute;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V_20230312_154030_attribute_option;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V_20230312_154040_payment;
use StoreKeeper\WooCommerce\B2C\Migrations\Versions\V_20230312_154050_refund;

class AllVersions implements VersionsInterface
{
    const VERSION_REGEX = '/\\V_(?<date>\d{8})_(?<time>\d{6})_\w+$/';
    const VERSION = [
        V_20230312_154000_webhook::class,
        V_20230312_154010_task::class,
        V_20230312_154020_attribute::class,
        V_20230312_154030_attribute_option::class,
        V_20230312_154040_payment::class,
        V_20230312_154050_refund::class,
    ];

    public function getVersionId(string $class): int
    {
        if (!preg_match(self::VERSION_REGEX, $class, $matches)) {
            throw new BaseException('Wrong version class naming: '.$class);
        }

        return  $matches['date'].$matches['time'];
    }

    public function getVersionsById(): array
    {
        $versions = [];
        foreach (self::VERSION as $class) {
            $versions[self::getVersionId($class)] = $class;
        }

        return $versions;
    }
}
