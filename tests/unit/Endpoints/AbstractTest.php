<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Endpoints;

use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\CommandRunnerTrait;

abstract class AbstractTest extends \StoreKeeper\WooCommerce\B2C\UnitTest\AbstractTest
{
    use CommandRunnerTrait;
    public const COMMON_DUMP_DIR = 'common';

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpRunner();
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->tearDownRunner();
    }

    /**
     * @throws \Exception
     */
    protected function mockApiCallsFromCommonDirectory(): void
    {
        $this->mockApiCallsFromDirectory(self::COMMON_DUMP_DIR, false);
    }
}
