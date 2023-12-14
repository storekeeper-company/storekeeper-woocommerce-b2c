<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\ShippingMethodImport;

class ShippingMethodDeleteTask extends AbstractTask
{
    public function run(array $task_options = []): void
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $storeKeeperId = $this->getTaskMeta('storekeeper_id');

            $import = new ShippingMethodImport();
            $import->deleteShippingMethodAndOrphanedShippingZones($storeKeeperId);
        }
    }
}
