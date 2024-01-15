<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\ShippingMethodImport;

class ShippingMethodImportTask extends AbstractTask
{
    public function run(array $task_options = []): void
    {
        $import = new ShippingMethodImport(
            [
                'storekeeper_id' => $this->getTaskMeta('storekeeper_id'),
            ]
        );

        $import->setLogger($this->logger);
        $import->run();
    }
}
