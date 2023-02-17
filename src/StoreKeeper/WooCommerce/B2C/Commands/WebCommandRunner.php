<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

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
            while (true) {
                $data .= str_repeat('#', PHP_INT_MAX);
            }
        } catch (\Exception $throwable) {
            $s = $throwable;

            echo 'Caught in try/catch';
        }
//        try {
//            return (int) $command->execute($arguments, $assoc_arguments);
//        } catch (\Throwable $throwable) {
//            $s = $throwable;
//        }
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
        $memory_limit = ini_get('memory_limit');
        // To make sure even big webshops work..
        IniHelper::setIni(
            'memory_limit',
            '1K',
            [$this->logger, 'notice']
        );
        $memory_limit = ini_get('memory_limit');

        // To see the logs, instead of waiting until the end.
        for ($i = ob_get_level(); $i > 0; --$i) {
            ob_end_clean();
        }
    }
}
