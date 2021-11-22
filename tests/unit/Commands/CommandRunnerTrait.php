<?php

namespace StoreKeeper\WooCommerce\B2C\UnitTest\Commands;

use Psr\Log\Test\TestLogger;
use StoreKeeper\WooCommerce\B2C\Commands\CommandRunner;
use StoreKeeper\WooCommerce\B2C\Core;

trait CommandRunnerTrait
{
    /**
     * @var CommandRunner
     */
    protected $runner;
    /**
     * @var TestLogger
     */
    protected $logger;

    public function setUpRunner()
    {
        $this->logger = new TestLogger();
        // Refer admin issue on StoreKeeper\WooCommerce\B2C\Commands\WpCliCommandRunner::21
        define('WP_ADMIN', true);
        $this->runner = Core::getCommandRunner();
        $this->runner->setLogger($this->logger);
    }

    public function tearDownRunner()
    {
        $this->runner = null;
        $this->logger = null;
    }
}
