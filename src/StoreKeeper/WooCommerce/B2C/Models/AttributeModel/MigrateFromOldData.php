<?php

namespace StoreKeeper\WooCommerce\B2C\Models\AttributeModel;

use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;

class MigrateFromOldData
{
    public const ATTRIBUTE_TABLE = 'storekeeper_woocommerce_attribute_metadata';

    public function run()
    {
        if ($this->tableExists($this->getTableName(self::ATTRIBUTE_TABLE))) {
            $sk_id_by_attribute = $this->getStoreKeeperIdsForAttributes();

            $attribute_taxonomies = wc_get_attribute_taxonomies();
            foreach ($attribute_taxonomies as $taxonomy) {
                if (array_key_exists($taxonomy->attribute_id, $sk_id_by_attribute)) {
                    $storekeeper_id = $sk_id_by_attribute[$taxonomy->attribute_id];
                    AttributeModel::setAttributeTaxonomy(
                        $taxonomy,
                        $storekeeper_id
                    );

                    TaskHandler::scheduleTask(TaskHandler::ATTRIBUTE_IMPORT, $storekeeper_id, [
                        'storekeeper_id' => $storekeeper_id,
                    ]);
                }
            }
        }
    }

    private function getStoreKeeperIdsForAttributes(): array
    {
        $tableName = $this->getTableName(self::ATTRIBUTE_TABLE);

        global $wpdb;

        $query = <<<SQL
SELECT `attribute_id`,`meta_value` as `storekeeper_id`
FROM `{$tableName}` 
WHERE `meta_key` = 'storekeeper_id'
GROUP BY `attribute_id`,`meta_value`;
SQL;
        $result = $wpdb->get_results($query);
        WordpressExceptionThrower::throwExceptionOnWpError($result, true);

        $sk_id_by_attribute = [];
        foreach ($result as $row) {
            if (!empty($row->storekeeper_id)) {
                $storekeeper_id = &$sk_id_by_attribute[$row->attribute_id];
                if (is_null($storekeeper_id) || $storekeeper_id < (int) $row->storekeeper_id) {
                    $storekeeper_id = (int) $row->storekeeper_id;
                }
            }
        }

        return $sk_id_by_attribute;
    }

    private static function getTableName(string $name): string
    {
        global $wpdb;

        return $wpdb->prefix.$name;
    }

    private static function tableExists(string $tableName): bool
    {
        global $wpdb;

        return $wpdb->get_var("SHOW TABLES LIKE '$tableName'") === $tableName;
    }
}
