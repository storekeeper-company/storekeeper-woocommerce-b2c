<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Tools\RedirectHandler;

class RedirectDeleteTask extends AbstractTask
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
        $this->debug('Deleting redirect', $this->getTaskMeta());

        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');

            RedirectHandler::deleteRedirect($storekeeper_id);
        }

        $this->debug('Deleted redirect', $this->getTaskMeta());

        return true;
    }
}
