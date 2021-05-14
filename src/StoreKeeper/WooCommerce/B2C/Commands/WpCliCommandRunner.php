<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use Monolog\Logger;
use StoreKeeper\WooCommerce\B2C\Core;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Factories\WpCliHandler;
use StoreKeeper\WooCommerce\B2C\Tools\IniHelper;

class WpCliCommandRunner extends CommandRunner
{
    const command_prefix = 'sk';

    /**
     * @throws \Exception
     */
    public function load()
    {
        foreach ($this->getCommands() as $name => $command) {
            $this->registerCommand($name);
        }
    }

    /**
     * @throws \Exception
     */
    private function registerCommand($name)
    {
        \WP_CLI::add_command(
            self::command_prefix.' '.$name,
            function (array $arguments, array $assoc_arguments) use ($name) {
                $this->execute($name, $arguments, $assoc_arguments);
            }
        );
    }

    /**
     * @param $name
     *
     * @throws \WP_CLI\ExitException
     */
    public function execute($name, array $arguments = [], array $assoc_arguments = []): int
    {
        try {
            $this->setConsoleLogger();
            $this->prepareExecution();
            $return = parent::execute($name, $arguments, $assoc_arguments);
            \WP_CLI::success('Command '.$name);
        } catch (\Throwable $e) {
            $this->exitWithException($name, $e);
        }

        return $return;
    }

    public function setConsoleLogger(): Logger
    {
        $log_level = LoggerFactory::getLogLevel();
        $logger = new Logger('wp-cli');
        $logger->pushHandler(
            new WpCliHandler($log_level)
        );
        $this->setLogger($logger);

        return $logger;
    }

    /**
     * this one will KILL the script.
     *
     * @throws \WP_CLI\ExitException
     */
    protected function exitWithException(string $name, \Throwable $e)
    {
        $debug = "=== Exception debug ====\n";
        $debug .= BaseException::getAsString($e);
        $this->logger->emergency(
            'Command failed with:'.$e->getMessage(),
            [
                'name' => $name,
                'exception' => $debug,
            ]
        );
        if (Core::isDebug()) {
            echo $debug;
        }
        $message = $e->getMessage();
        $code = 2;
        if (!($e instanceof BaseException)) {
            $code = 255;
        }
        \WP_CLI::error($message, $code);
    }

    private function prepareExecution()
    {
        // To make sure even big webshops work..
        IniHelper::setIni(
            'memory_limit',
            0,
            [$this->logger, 'warning']
        );

        // To see the logs, instead of waiting until the end.
        for ($i = ob_get_level(); $i > 0; --$i) {
            ob_end_clean();
        }
    }

    protected function printError(\Throwable $e)
    {
        echo BaseException::getAsString($e);
    }
}
