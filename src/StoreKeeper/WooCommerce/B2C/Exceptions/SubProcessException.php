<?php

namespace StoreKeeper\WooCommerce\B2C\Exceptions;

use Symfony\Component\Process\Process;

class SubProcessException extends BaseException
{
    /**
     * @var Process
     */
    protected $process;

    /**
     * SubProcessException constructor.
     */
    public function __construct(Process $process, string $cmd_string)
    {
        $this->process = $process;
        parent::__construct(
            "Command '$cmd_string' failed. ",
            $process->getExitCode() ?? 0
        );
    }

    public function getProcess(): Process
    {
        return $this->process;
    }
}
