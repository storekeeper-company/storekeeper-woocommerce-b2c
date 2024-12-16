<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\LocationImport;

class LocationImportTask extends AbstractTask
{

    public function run(array $task_options = []): void
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $locationImport = $this->getImporterInstance([
                'storekeeper_id' => (int) $this->getTaskMeta('storekeeper_id'),
                'debug' => array_key_exists('debug', $task_options) ? (bool) $task_options['debug'] : false
            ]);

            $locationImport->setTaskHandler($this->getTaskHandler());
            $locationImport->setLogger($this->logger);
            $locationImport->run();
        } else {
            $this->logger->notice('No storekeeper_id -> nothing to process');
        }
    }

    protected function getImporterInstance(array $settings = []): LocationImport
    {
        return new LocationImport($settings);
    }
}
