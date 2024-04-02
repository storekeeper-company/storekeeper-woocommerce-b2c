<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest;

use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\CommandRunnerTrait;

abstract class AbstractProductTest extends AbstractTest
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
    public function mockApiCallsFromCommonDirectory(): void
    {
        $this->mockApiCallsFromDirectory(self::COMMON_DUMP_DIR, false);
    }
}
