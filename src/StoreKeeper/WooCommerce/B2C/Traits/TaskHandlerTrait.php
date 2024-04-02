<?php

namespace StoreKeeper\WooCommerce\B2C\Traits;

trait TaskHandlerTrait
{
    protected $taskHandler;

    public function setTaskHandler($taskHandler)
    {
        $this->taskHandler = $taskHandler;
    }

    public function getTaskHandler()
    {
        return $this->taskHandler;
    }
}
