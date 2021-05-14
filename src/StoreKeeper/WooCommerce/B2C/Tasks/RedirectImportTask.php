<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\RedirectImport;

class RedirectImportTask extends AbstractTask
{
    /**
     * @param $task_options array
     *
     * @return bool returns true in the import was succeeded
     *
     * @throws \Exception
     */
    public function run($task_options = [])
    {
        // Check if the meta has an storekeeper id
        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');

            // Run the import task
            $tag = new RedirectImport(
                [
                    'storekeeper_id' => $storekeeper_id,
                    'debug' => key_exists('debug', $task_options) ? $task_options['debug'] : false,
                ]
            );
            $tag->run();
        }

        return true;
    }
}
