<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\LocationUpdateImport;
use StoreKeeper\WooCommerce\B2C\Imports\LocationImport;

class LocationUpdateTask extends AbstractTask
{

    public function run(array $task_options = []): void
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $locationImport = new LocationUpdateImport(
                [
                    'storekeeper_id' => (string) $this->getTaskMeta('storekeeper_id'),
                    'scope' => $this->getTaskMeta('scope') ?? null,
                    'debug' => array_key_exists('debug', $task_options) ? (bool) $task_options['debug'] : false
                ]
            );

            $locationImport->setTaskHandler($this->getTaskHandler());
            $locationImport->setLogger($this->logger);
            $locationImport->run();
        } else {
            $this->logger->notice('No storekeeper_id -> nothing to process');
        }
    }

    protected function getImporterInstance(array $settings = []): LocationImport
    {
        $settings['scope'] = $this->getTaskMeta('scope') ?? null;

        return new LocationUpdateImport($settings);
    }
}
