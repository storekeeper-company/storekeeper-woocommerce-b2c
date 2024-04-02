<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands;

use StoreKeeper\WooCommerce\B2C\I18N;

abstract class AbstractModelGetCommand extends AbstractModelCommand
{
    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'positional',
                'name' => 'id',
                'description' => __('The ID of the object to be retrieved.', I18N::DOMAIN),
                'optional' => false,
            ],
        ];
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        global $wpdb;

        if (empty($arguments[0])) {
            throw new \Exception('Please give an id as its first parameters');
        }

        $Model = $this->getModel();

        $id = $arguments[0];

        $select = $Model::getSelectHelper()
            ->cols(['*'])
            ->where('id = :id')
            ->bindValue('id', $id)
            ->limit(1);

        $data = $wpdb->get_row($Model::prepareQuery($select), ARRAY_A);

        if (!$data) {
            \WP_CLI::error("Item with ID $id not found");
        }

        $formatter = new \WP_CLI\Formatter($assoc_arguments, array_keys($Model::getFieldsWithRequired()));

        $formatter->display_item($data);
    }
}
