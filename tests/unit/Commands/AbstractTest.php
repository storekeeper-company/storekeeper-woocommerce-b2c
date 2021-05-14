<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

abstract class AbstractTest extends \StoreKeeper\WooCommerce\B2C\UnitTest\AbstractTest
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
