<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands;

use Exception;

abstract class AbstractModelDeleteCommand extends AbstractModelCommand
{
    public function execute(array $arguments, array $assoc_arguments)
    {
        global $wpdb;

        if (empty($arguments[0])) {
            throw new Exception('Please give an id as its first parameters');
        }

        $Model = $this->getModel();

        $id = $arguments[0];

        $delete = $Model::getDeleteHelper()
            ->where('id = :id')
            ->bindValue('id', $id)
            ->limit(1);

        $affectedRows = $wpdb->query($Model::prepareQuery($delete));

        $Model::ensureAffectedRows($affectedRows);

        \WP_CLI::success(
            sprintf(
                '%d affected rows.',
                $affectedRows
            )
        );
    }
}
