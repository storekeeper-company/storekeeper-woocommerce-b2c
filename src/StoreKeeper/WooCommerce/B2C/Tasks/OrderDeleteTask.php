<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

class OrderDeleteTask extends AbstractTask
{
    public function run($task_options = [])
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeper_id = $this->getTaskMeta('storekeeper_id');
            if ($this->externalOrderExists($storekeeper_id)) {
                $this->storekeeper_api->getModule('ShopModule')->deleteExternalOrder($storekeeper_id);
            }
        }

        return true;
    }

    private function externalOrderExists($storekeeper_id)
    {
        $response = $this->storekeeper_api->getModule('ShopModule')->listExternalOrders(
            0,
            1,
            null,
            [
                [
                    'name' => 'id__=',
                    'val' => $storekeeper_id,
                ],
            ]
        );

        return $response['count'] > 0;
    }
}
