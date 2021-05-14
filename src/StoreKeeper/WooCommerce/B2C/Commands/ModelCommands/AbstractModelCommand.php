<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands;

use StoreKeeper\WooCommerce\B2C\Commands\AbstractCommand;
use StoreKeeper\WooCommerce\B2C\Models\AbstractModel;

abstract class AbstractModelCommand extends AbstractCommand
{
    abstract public function getModelClass(): string;

    protected function getModel(): AbstractModel
    {
        $ModelClass = $this->getModelClass();

        return new $ModelClass();
    }
}
