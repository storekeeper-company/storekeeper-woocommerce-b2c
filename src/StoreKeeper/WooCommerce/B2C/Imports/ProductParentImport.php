<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class ProductParentImport extends ProductImport
{
    protected function handleUpsellProducts($newProduct, Dot $dotObject, array $options, array $log_data): array
    {
        $this->debug('Skipped importing upsell products on parent product', $options);
        $shopProductId = (int) $dotObject->get('id');
        $task = TaskHandler::scheduleTask(TaskHandler::PRODUCT_UPDATE, $shopProductId, [
            'scope' => ProductUpdateImport::PRODUCT_UP_SELL_SCOPE,
            'storekeeper_id' => $shopProductId,
        ]);
        $taskId = $task['id'];
        $this->debug("Scheduled a new task (id=$taskId) for product update focusing up sell products", $options);

        return $log_data;
    }

    protected function handleCrossSellProducts($newProduct, Dot $dotObject, array $options, array $log_data): array
    {
        $this->debug('Skipped importing cross sell products on parent product', $options);
        $shopProductId = (int) $dotObject->get('id');
        $task = TaskHandler::scheduleTask(TaskHandler::PRODUCT_UPDATE, $shopProductId, [
            'scope' => ProductUpdateImport::PRODUCT_CROSS_SELL_SCOPE,
            'storekeeper_id' => $shopProductId,
        ]);
        $taskId = $task['id'];
        $this->debug("Scheduled a new task (id=$taskId) for product update focusing cross sell products", $options);

        return $log_data;
    }
}
