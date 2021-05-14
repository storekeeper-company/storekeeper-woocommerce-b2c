<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\OrderImport;

class OrderImportTask extends AbstractTask
{
    /**
     * @param $task_options
     *
     * @return array|bool
     *
     * @throws \Exception
     */
    public function run($task_options = [])
    {
        if (
            $this->taskMetaExists('storekeeper_id') &&
            $this->taskMetaExists('order') &&
            $this->taskMetaExists('old_order')
        ) {
            $new_order = $this->getTaskMeta('order');
            $old_order = $this->getTaskMeta('old_order');
            $order = new OrderImport(
                [
                    'storekeeper_id' => $this->getTaskMeta('storekeeper_id'),
                    'old' => json_decode($old_order, true),
                    'new' => json_decode($new_order, true),
                ]
            );

            return $order->run();
        }

        return true;
    }
}
