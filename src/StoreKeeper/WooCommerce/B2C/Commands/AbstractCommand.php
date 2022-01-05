<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use ReflectionClass;
use StoreKeeper\ApiWrapper\ApiWrapper;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Exceptions\NotConnectedException;
use StoreKeeper\WooCommerce\B2C\Exceptions\SubProcessException;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

abstract class AbstractCommand implements CommandInterface
{
    use LoggerAwareTrait;

    const AMOUNT = 100;

    /**
     * @var ApiWrapper
     */
    protected $api;
    /**
     * @var CommandRunner
     */
    protected $runner;
    /**
     * @var Guard
     */
    private $lock;

    /**
     * AbstractCommand constructor.
     */
    public function __construct()
    {
        $this->setLogger(new NullLogger());
    }

    /**
     * @throws Exception
     */
    public function lock()
    {
        $this->lock = new Guard(get_called_class());

        return $this->lock->lock();
    }

    public function getRunner(): CommandRunner
    {
        return $this->runner;
    }

    public function setRunner(CommandRunner $runner): void
    {
        $this->runner = $runner;
    }

    protected static function generateExamples(array $examples): string
    {
        $exampleBuilder = "\n\n## EXAMPLES\n\n";
        $exampleBuilder .= implode("\n\n", $examples);

        return $exampleBuilder;
    }

    /**
     * @param AbstractCommand $command
     */
    public static function getCommandName(): string
    {
        $reflect = new ReflectionClass(get_called_class());
        $className = $reflect->getShortName();
        // Changes CamelCase to snake-case
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $className, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }

        return implode('-', $ret);
    }

    /**
     * @throws NotConnectedException
     */
    protected function setupApi()
    {
        if (!StoreKeeperOptions::isConnected()) {
            $url = get_site_url(null, 'wp-admin/admin.php?page=wc-settings&tab=storekeeper-woocommerce-b2c');
            throw new NotConnectedException("No backend connected, please configure one at: $url");
        }

        $this->api = StoreKeeperApi::getApiByAuthName();
    }

    /**
     * @param $name
     *
     * @throws BaseException
     */
    protected function executeSubCommand(
        $name,
        array $arguments = [],
        array $assoc_arguments = [],
        bool $isOutputEcho = false
    ): int {
        if (Core::isTest()) {
            // for tests we skip all the sub processing,
            // to make sure all the mocks we set up are the same
            $result = $this->runner->execute($name, $arguments, $assoc_arguments);
            $this->logger->warning(__CLASS__.'::'.__FILE__.' timeout option ignored');
        } else {
            $result = $this->runner->executeAsSubProcess($name, $arguments, $assoc_arguments, $isOutputEcho);
        }

        return $result;
    }

    /**
     * Runs a command with pagination.
     *
     * @param string $command_name The name of the command to run
     * @param int    $total_amount The total amount of items to send to the command
     * @param int    $amount       The amount of items to send to the command at once
     *
     * @throws SubProcessException|BaseException
     */
    protected function runSubCommandWithPagination(string $command_name, int $total_amount, int $amount = self::AMOUNT, bool $isOutputEcho = false): void
    {
        $page = 0;
        for ($start = 0; $start < $total_amount; $start += $amount) {
            $this->executeSubCommand(
                $command_name,
                [],
                [
                    'start' => $start,
                    'limit' => $amount,
                    'page' => $page,
                ],
                $isOutputEcho
            );
            ++$page;
        }
    }
}
