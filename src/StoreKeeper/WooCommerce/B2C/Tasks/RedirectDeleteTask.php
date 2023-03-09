<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Tools\RedirectHandler;

class RedirectDeleteTask extends AbstractTask
{
    /**
     * @param $task_options array
     *
     * @return void returns true in the import was succeeded
     *
     * @throws \Exception
     */
    public function run(array $task_options = []): void
    {
        $this->debug('Deleting redirect', $this->getTaskMeta());

        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');

            RedirectHandler::deleteRedirect($storekeeper_id);
        }

        $this->debug('Deleted redirect', $this->getTaskMeta());
    }
}
