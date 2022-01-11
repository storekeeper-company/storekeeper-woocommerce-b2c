<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Imports\AttributeOptionImport;

class SyncWoocommerceAttributeOptionPage extends AbstractSyncCommand
{
    public static function getShortDescription(): string
    {
        return __('Sync product attribute options with limit and offset.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Sync product attribute options from Storekeeper Backoffice with limit and offset. Note that this should be executed when attributes are already synchronized.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'assoc',
                'name' => 'start',
                'description' => __('Skip other attribute options and synchronize from specified starting point.', I18N::DOMAIN),
                'optional' => false,
            ],
            [
                'type' => 'assoc',
                'name' => 'limit',
                'description' => __('Determines how many attribute options will be synchronized from the starting point.', I18N::DOMAIN),
                'optional' => false,
            ],
            [
                'type' => 'flag',
                'name' => 'hide-progress-bar',
                'description' => __('Hide displaying of progress bar while executing command.', I18N::DOMAIN),
                'optional' => true,
            ],
        ];
    }

    public function execute(array $arguments, array $assoc_arguments)
    {
        if ($this->prepareExecute()) {
            if (key_exists('limit', $assoc_arguments) && key_exists('start', $assoc_arguments)) {
                $this->runWithPagination($assoc_arguments);
            } else {
                throw new BaseException('Limit and start attribute need to be set');
            }
        }
    }

    public function runWithPagination($assoc_arguments)
    {
        $import = new AttributeOptionImport($assoc_arguments);
        $import->setLogger($this->logger);
        $import->run();
    }
}
