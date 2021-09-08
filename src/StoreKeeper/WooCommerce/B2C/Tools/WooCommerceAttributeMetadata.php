<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

class WooCommerceAttributeMetadata
{
    const DATATABLE_NAME = 'storekeeper_woocommerce_attribute_metadata';

    /**
     * @return string
     */
    public static function getTableName()
    {
        global $wpdb;

        return $wpdb->prefix.self::DATATABLE_NAME;
    }

    /**
     * @return bool
     */
    public static function databaseTableExists()
    {
        global $wpdb;
        $table_name = self::getTableName();

        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }

    /**
     * @throws \Exception
     */
    private static function databaseTableExistsCheck()
    {
        if (!self::databaseTableExists()) {
            $table_name = self::getTableName();
            throw new \Exception("Database table '$table_name' does not exists");
        }
    }

    public static function createTable()
    {
        global $wpdb;

        if (!self::databaseTableExists()) {
            require_once ABSPATH.'wp-admin/includes/upgrade.php';
            $table_name = self::getTableName();

            /**
             * Create table.
             */
            $sqlCreate = <<<SQL
CREATE TABLE `$table_name` (
  `meta_id` bigint(20) UNSIGNED NOT NULL,
  `attribute_id` bigint(20) UNSIGNED NOT NULL,
  `meta_key` varchar(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `meta_value` longtext COLLATE utf8mb4_unicode_ci
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
            if (false === $wpdb->query($sqlCreate)) {
                throw new \Exception($wpdb->last_error);
            }

            /**
             * Setups indexing.
             */
            $sqlIndexing = <<<SQL
ALTER TABLE `$table_name`
  ADD PRIMARY KEY (`meta_id`),
  ADD KEY `attribute_id` (`attribute_id`),
  ADD KEY `meta_key` (`meta_key`);
SQL;
            if (false === $wpdb->query($sqlIndexing)) {
                throw new \Exception($wpdb->last_error);
            }

            /**
             * Setups Auto increment.
             */
            $sqlAutoIncrement = <<<SQL
ALTER TABLE `$table_name`
  MODIFY `meta_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;
SQL;
            if (false === $wpdb->query($sqlAutoIncrement)) {
                throw new \Exception($wpdb->last_error);
            }

            self::setVersion('1.0');
        }
    }

    private static function setVersion($version)
    {
        update_option(self::DATATABLE_NAME.'_version', $version);
    }

    public static function getVersion()
    {
        return get_option(self::DATATABLE_NAME.'_version');
    }

    /**
     * @param $attribute_id
     * @param $meta_key
     * @param $meta_value
     *
     * @throws \Exception
     */
    public static function setMetadata($attribute_id, $meta_key, $meta_value)
    {
        self::databaseTableExistsCheck();
        if (!is_null(wc_get_attribute($attribute_id))) {
            global $wpdb;
            $table_name = self::getTableName();
            $wpdb->insert(
                $table_name,
                [
                    'attribute_id' => $attribute_id,
                    'meta_key' => $meta_key,
                    'meta_value' => $meta_value,
                ]
            );
        }
    }

    /**
     * @param $attribute_id
     * @param $meta_key
     *
     * @return mixed
     *
     * @throws \Exception
     */
    public static function getMetadata($attribute_id, $meta_key, bool $single = true, $order = 'ASC')
    {
        self::databaseTableExistsCheck();
        if (!is_null(wc_get_attribute($attribute_id))) {
            global $wpdb;
            $table_name = self::getTableName();
            $sql = <<<SQL
            SELECT meta_value 
            FROM `$table_name` 
            WHERE `attribute_id` = %d 
            AND `meta_key` = %s
            ORDER BY meta_id $order
SQL;

            $sqlPrepared = $wpdb->prepare($sql, $attribute_id, $meta_key);
            $row = $wpdb->get_row($sqlPrepared);

            if (!is_null($row)) {
                if ($single) {
                    return current($row);
                }

                return $row;
            }
        }

        return null;
    }

    /**
     * @param $attribute_id
     * @param $meta_key
     *
     * @throws \Exception
     */
    public static function deleteMetadata($attribute_id, $meta_key)
    {
        self::databaseTableExistsCheck();
        if (!is_null(wc_get_attribute($attribute_id))) {
            global $wpdb;
            $table_name = self::getTableName();
            $wpdb->delete(
                $table_name,
                [
                    'attribute_id' => $attribute_id,
                    'meta_key' => $meta_key,
                ]
            );
        }
    }

    /**
     * @param $arguments array meta_data:required - meta_key:optional - number:optional - single:optional
     *
     * @return array<stdClass>|stdClass|false
     *
     * @throws \Exception
     */
    public static function listAttributesByMetadata($arguments)
    {
        if (!key_exists('meta_key', $arguments)) {
            throw new \Exception("The 'meta_data' key is required");
        }

        global $wpdb;

        $table_name = self::getTableName();
        $wooCommerce_table_name = $wpdb->prefix.'woocommerce_attribute_taxonomies';
        $data = [];

        $sqlWhere = "WHERE attribute_meta.meta_key = %s\r\n";
        $data[] = $arguments['meta_key'];
        if (key_exists('meta_value', $arguments)) {
            $sqlWhere .= "AND attribute_meta.meta_value = %s\r\n";
            $data[] = $arguments['meta_value'];
        }

        $sqlSingle = '';
        if (key_exists('number', $arguments)) {
            $sqlSingle .= 'LIMIT %d';
            $data[] = $arguments['number'];
        } else {
            if (key_exists('single', $arguments)) {
                $sqlSingle .= 'LIMIT %d';
                $data[] = 1;
            }
        }

        $sql = "
        SELECT * 
        FROM $table_name as attribute_meta
        INNER JOIN $wooCommerce_table_name as attribute 
        ON attribute_meta.attribute_id=attribute.attribute_id
        $sqlWhere
        $sqlSingle
        ";

        $sqlPrepared = $wpdb->prepare($sql, $data);

        $results = $wpdb->get_results($sqlPrepared);

        $return = array_map(
            function ($result) {
                return wc_get_attribute($result->attribute_id);
            },
            $results
        );

        if (key_exists('single', $arguments) && (bool) $arguments['single']) {
            return current($return);
        }

        return $return;
    }
}
