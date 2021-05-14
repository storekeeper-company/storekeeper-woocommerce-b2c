<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\MenuItemImport;

class MenuItemImportTask extends AbstractTask
{
    /**
     * @param $task_options
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function run($task_options = [])
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

            return $this->throwExceptionArray($tag->run());
        }

        return true;
    }
}
