<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Imports\CouponCodeImport;

class CouponCodeImportTask extends AbstractTask
{
    public function run(array $task_options = []): void
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');
            $coupon_code_import = new CouponCodeImport(
                [
                    'code' => $this->getTaskMeta('code'),
                    'storekeeper_id' => $storekeeper_id,
                    'debug' => key_exists('debug', $task_options) ? $task_options['debug'] : false,
                ]
            );

            $coupon_code_import->setLogger($this->logger);
            $coupon_code_import->run();
        }
    }
}
