<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest;

use Exception;
use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\CommandRunnerTrait;

abstract class AbstractProductTest extends AbstractTest
{
    use CommandRunnerTrait;
    const COMMON_DUMP_DIR = 'common';

    public function setUp()
    {
        parent::setUp();
        $this->setUpRunner();
    }

    public function tearDown()
    {
        parent::tearDown();
        $this->tearDownRunner();
    }

    /**
     * @throws Exception
     */
    public function mockApiCallsFromCommonDirectory(): void
    {
        $this->mockApiCallsFromDirectory(self::COMMON_DUMP_DIR, false);
    }
}
