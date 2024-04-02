<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\Task;

use StoreKeeper\WooCommerce\B2C\Commands\ModelCommands\AbstractModelListCommand;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class TaskList extends AbstractModelListCommand
{
    public const AVAILABLE_STATUSES = [
        TaskHandler::STATUS_NEW,
        TaskHandler::STATUS_FAILED,
        TaskHandler::STATUS_PROCESSING,
        TaskHandler::STATUS_SUCCESS,
    ];

    public static function getCommandName(): string
    {
        return 'task list';
    }

    public function getModelClass(): string
    {
        return TaskModel::class;
    }

    public static function getShortDescription(): string
    {
        return __('Retrieve all tasks.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Retrieve all tasks from the database.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'assoc',
                'name' => 'status',
                'description' => __('The status of tasks to be retrieved.', I18N::DOMAIN),
                'optional' => true,
                'options' => self::AVAILABLE_STATUSES,
            ],
        ];
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        global $wpdb;

        $Model = $this->getModel();

        $select = $Model::getSelectHelper()
            ->cols(['*'])
            ->orderBy(['date_created DESC', 'id DESC'])
            ->limit(100);

        if (array_key_exists('status', $assoc_arguments)) {
            $wantedStatus = $assoc_arguments['status'];
            $status = $this->getCurrentStatus($wantedStatus);
            if (!$status) {
                \WP_CLI::error(
                    "Unknown status '$wantedStatus', write parts of one of the following once: ".join(
                        ', ',
                        self::AVAILABLE_STATUSES
                    )
                );
            }
            $select
                ->where('status = :status')
                ->bindValue('status', $this->getCurrentStatus($status));
        }

        $data = $wpdb->get_results($Model::prepareQuery($select), ARRAY_A);

        $formatter = new \WP_CLI\Formatter($assoc_arguments, array_keys($Model::getFieldsWithRequired()));

        $formatter->display_items($data);
    }

    private function getCurrentStatus(string $wantedStatus)
    {
        $status = null;
        foreach (self::AVAILABLE_STATUSES as $availableStatus) {
            if (!$status && false !== strpos($availableStatus, $wantedStatus)) {
                $status = $availableStatus;
            }
        }

        return $status;
    }
}
