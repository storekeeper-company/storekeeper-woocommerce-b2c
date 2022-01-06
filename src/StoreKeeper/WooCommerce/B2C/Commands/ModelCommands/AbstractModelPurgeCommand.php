<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands;

abstract class AbstractModelPurgeCommand extends AbstractModelCommand
{
    public static function getSynopsis(): array
    {
        return []; // No synopsis
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        $Model = $this->getModel();

        $affectedRows = $Model::purge();

        \WP_CLI::success(
            sprintf(
                '%d affected rows.',
                $affectedRows
            )
        );
    }
}
