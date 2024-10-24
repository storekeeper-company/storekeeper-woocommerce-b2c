<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

abstract class AbstractTest extends \StoreKeeper\WooCommerce\B2C\UnitTest\AbstractTest
{
    use CommandRunnerTrait;
    public const COMMON_DUMP_DIR = 'common';

    public function setUp(): void
    {
        parent::setUp();
        $this->setUpRunner();
        do_action('woocommerce_init');
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
