<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands;

abstract class AbstractModelListCommand extends AbstractModelCommand
{
    public static function getSynopsis(): array
    {
        return []; // No synopsis
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        global $wpdb;

        $Model = $this->getModel();

        $select = $Model::getSelectHelper()
            ->cols(['*'])
            ->orderBy(['date_created DESC', 'id DESC'])
            ->limit(100);

        $data = $wpdb->get_results($Model::prepareQuery($select), ARRAY_A);

        $formatter = new \WP_CLI\Formatter($assoc_arguments, array_keys($Model::getFieldsWithRequired()));

        $formatter->display_items($data);
    }
}
