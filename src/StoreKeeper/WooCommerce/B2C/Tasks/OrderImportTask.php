<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\OrderImport;

class OrderImportTask extends AbstractTask
{
    /**
     * @throws \Exception
     */
    public function run(array $task_options = []): void
    {
        if (
            $this->taskMetaExists('storekeeper_id')
            && $this->taskMetaExists('order')
            && $this->taskMetaExists('old_order')
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
            $order->setLogger($this->logger);
            $order->run();
        }
    }
}
