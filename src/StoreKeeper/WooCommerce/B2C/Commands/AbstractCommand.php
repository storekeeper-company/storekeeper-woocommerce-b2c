<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use StoreKeeper\ApiWrapper\ApiWrapper;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Database\MySqlLock;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Exceptions\LockActiveException;
use StoreKeeper\WooCommerce\B2C\Exceptions\NotConnectedException;
use StoreKeeper\WooCommerce\B2C\Exceptions\SubProcessException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\LockInterface;
use StoreKeeper\WooCommerce\B2C\Interfaces\WithConsoleProgressBarInterface;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Traits\ConsoleProgressBarTrait;

abstract class AbstractCommand implements CommandInterface, WithConsoleProgressBarInterface
{
    use LoggerAwareTrait;
    use ConsoleProgressBarTrait;

    public const AMOUNT = 100;

    /**
     * @var ApiWrapper
     */
    protected $api;
    /**
     * @var CommandRunner
     */
    protected $runner;
    /**
     * @var LockInterface
     */
    protected $lock;

    /**
     * AbstractCommand constructor.
     */
    public function __construct()
    {
        $this->setLogger(new NullLogger());
    }

    /**
     * @throws LockActiveException
     * @throws \Exception
     */
    public function lock(): bool
    {
        $lockClass = $this->getLockClass();
        $this->lock = new MySqlLock($lockClass);

        $this->logger->debug('Trying to lock command', [
            'lockClass' => $lockClass,
        ]);

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
        $exampleBuilder = "\n\n## ".__('EXAMPLES', I18N::DOMAIN)."\n\n";
        $exampleBuilder .= implode("\n\n", $examples);

        return $exampleBuilder;
    }

    public static function getCommandName(): string
    {
        $reflect = new \ReflectionClass(get_called_class());
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
     * @throws BaseException
     */
    protected function executeSubCommand(
        $name,
        array $arguments = [],
        array $assoc_arguments = [],
        bool $isOutputEcho = false,
        bool $hideSubprocessProgressBar = false
    ): int {
        if (Core::isTest()) {
            // for tests we skip all the sub processing,
            // to make sure all the mocks we set up are the same
            $result = $this->runner->execute($name, $arguments, $assoc_arguments);
            $this->logger->warning(__CLASS__.'::'.__FILE__.' timeout option ignored');
        } else {
            $result = $this->runner->executeAsSubProcess($name, $arguments, $assoc_arguments, $isOutputEcho, $hideSubprocessProgressBar);
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
    protected function runSubCommandWithPagination(
        string $command_name,
        int $total_amount,
        int $amount = self::AMOUNT,
        bool $isOutputEcho = false,
        bool $hasPageArgument = false,
        bool $hasStartArgument = false,
        bool $hideSubprocessProgressBar = false
    ): void {
        $page = 0;
        $this->createProgressBar(ceil($total_amount / $amount), __('Syncing from Storekeeper backoffice', I18N::DOMAIN));
        for ($start = 0; $start < $total_amount; $start += $amount) {
            $assocArguments = [
                'limit' => $amount,
            ];

            if ($hasStartArgument) {
                $assocArguments['start'] = $start;
            }

            if ($hasPageArgument) {
                $assocArguments['page'] = $page;
            }

            $this->executeSubCommand(
                $command_name,
                [],
                $assocArguments,
                $isOutputEcho,
                $hideSubprocessProgressBar
            );
            $this->tickProgressBar();
            ++$page;
        }

        $this->endProgressBar();
    }

    protected function getLockClass(): string
    {
        return get_class($this);
    }
}
