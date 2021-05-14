<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest;

use StoreKeeper\WooCommerce\B2C\UnitTest\Commands\CommandRunnerTrait;

abstract class AbstractProductTest extends AbstractTest
{
    use CommandRunnerTrait;

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
}
