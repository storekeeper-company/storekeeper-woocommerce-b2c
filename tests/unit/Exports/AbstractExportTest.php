<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Exports;

use Faker\Factory;
use Faker\Generator;
use StoreKeeper\WooCommerce\B2C\UnitTest\AbstractTest;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\CommandRunnerTrait;

abstract class AbstractExportTest extends AbstractTest
{
    use CommandRunnerTrait;

    /**
     * @var Generator
     */
    protected $faker;

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpRunner();
        update_option('woocommerce_currency', 'EUR');
        $this->faker = Factory::create();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->tearDownRunner();
    }
}
