<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Models\LocationModel;

class LocationDeactivateTask extends AbstractTask
{

    public function run(array $taskOptions = []): void
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $storekeeperId = (int) $this->getTaskMeta('storekeeper_id');
            $location = LocationModel::getByStoreKeeperId($storekeeperId);

            if (null !== $location && $location['is_active']) {
                LocationModel::update($location[LocationModel::PRIMARY_KEY], ['is_active' => false]);
            }
        } else {
            $this->logger->notice('No storekeeper_id -> nothing to process');
        }
    }
}
