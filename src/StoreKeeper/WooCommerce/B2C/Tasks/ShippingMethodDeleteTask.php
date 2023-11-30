<?php

namespace StoreKeeper\WooCommerce\B2C\Tasks;

use StoreKeeper\WooCommerce\B2C\Models\ShippingMethodModel;
use StoreKeeper\WooCommerce\B2C\Models\ShippingZoneModel;

class ShippingMethodDeleteTask extends AbstractTask
{
    public function run(array $task_options = []): void
    {
        if ($this->taskMetaExists('storekeeper_id')) {
            $storeKeeperId = $this->getTaskMeta('storekeeper_id');

            $skShippingZoneIds = ShippingMethodModel::getShippingZoneIdsByStoreKeeperId($storeKeeperId);

            if (!is_null($skShippingZoneIds)) {
                $findByIds = implode(',', $skShippingZoneIds);
                $skShippingZones = ShippingZoneModel::findBy(
                    ["id IN ($findByIds)"],
                );

                if (!empty($skShippingZones)) {
                    foreach ($skShippingZones as $skShippingZone) {
                        $wcShippingMethodInstanceId = ShippingMethodModel::getInstanceIdByShippingZoneAndStoreKeeperId(
                            $skShippingZone['id'],
                            $storeKeeperId
                        );

                        $woocommerceZone = new \WC_Shipping_Zone($skShippingZone['wc_zone_id']);
                        $woocommerceZone->delete_shipping_method($wcShippingMethodInstanceId);
                        $woocommerceZone->save();
                        ShippingMethodModel::deleteByInstanceId($wcShippingMethodInstanceId);

                        if (0 === count($woocommerceZone->get_shipping_methods())) {
                            $woocommerceZone->delete(true);
                            ShippingZoneModel::delete($skShippingZone['id']);
                        }
                    }
                }
            }
        }
    }
}
