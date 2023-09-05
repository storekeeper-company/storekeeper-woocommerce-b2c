<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Exceptions\LockException;
use StoreKeeper\WooCommerce\B2C\Tools\IniHelper;

class WebCommandRunner extends CommandRunner
{
    public function execute($name, array $arguments = [], array $assoc_arguments = []): int
    {
        $this->prepareExecution();
        $class = $this->getCommandClass($name);
        /* @var $command AbstractCommand */
        $command = new $class();
        $command->setRunner($this);
        try {
            $result = (int) $command->execute($arguments, $assoc_arguments);
        } catch (LockException $e) {
            $result = self::LOCK_EXIT;
            $this->logger->notice('There is another process holding the lock (cannot run)', [
                'message' => $e->getMessage(),
                'trace' => $e->getTrace(),
            ]);
        }

        return (int) $result;
    }

    public function executeAsSubProcess(
        string $name, array $arguments = [],
        array $assoc_arguments = [],
        bool $isOutputEcho = false,
        bool $hideSubprocessProgressBar = true
    ): int {
        // web runner cannot execute as subprocess, just a normal execute instead
        return $this->execute($name, $arguments, $assoc_arguments);
    }

    private function prepareExecution()
    {
        // To make sure even big webshops work..
        IniHelper::setIni(
            'memory_limit',
            0,
            [$this->logger, 'notice']
        );

        // To see the logs, instead of waiting until the end.
        for ($i = ob_get_level(); $i > 0; --$i) {
            ob_end_clean();
        }
    }
}
