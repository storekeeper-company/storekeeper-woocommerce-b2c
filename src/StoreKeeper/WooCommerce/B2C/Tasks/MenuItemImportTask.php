<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\MenuItemImport;

class MenuItemImportTask extends AbstractTask
{
    /**
     * @throws \Exception
     */
    public function run(array $task_options = []): void
    {
        // Check if the meta has an storekeeper id
        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');

            // Run the import task
            $tag = new MenuItemImport(
                [
                    'storekeeper_id' => $storekeeper_id,
                    'debug' => key_exists('debug', $task_options) ? $task_options['debug'] : false,
                ]
            );
            $tag->setLogger($this->logger);
            $tag->run();
        }
    }
}
