<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

class RedirectHandler
{
    public const DATATABLE_NAME = 'storekeeper_redirects';

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

    /**
     * Creates the redirection database table.
     */
    public static function createTable(): bool
    {
        global $wpdb;

        if (!self::databaseTableExists()) {
            require_once ABSPATH.'wp-admin/includes/upgrade.php';
            $table_name = self::getTableName();

            /**
             * Initialisation.
             */

            /**
             * Create table.
             */
            $sqlCreate = <<<SQL
CREATE TABLE `$table_name` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `storekeeper_id` bigint(20) UNSIGNED NOT NULL,
  `from_url` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_url` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_code` int UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE (`storekeeper_id`)
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
            if (false === $wpdb->query($sqlCreate)) {
                throw new \Exception($wpdb->last_error);
            }

            self::setVersion('1.0');

            return true;
        }

        return false;
    }

    /**
     * @param int $storekeeper_id
     *
     * @throws \Exception
     */
    public static function deleteRedirect($storekeeper_id)
    {
        self::databaseTableExistsCheck();
        global $wpdb;
        $table_name = self::getTableName();
        $wpdb->delete(
            $table_name,
            [
                'storekeeper_id' => $storekeeper_id,
            ]
        );
    }

    /**
     * @param int $version
     */
    private static function setVersion($version)
    {
        update_option(self::DATATABLE_NAME.'_version', $version);
    }

    /**
     * @return mixed|void
     */
    public static function getVersion()
    {
        return get_option(self::DATATABLE_NAME.'_version');
    }

    /**
     * @param int    $storekeeper_id
     * @param string $from_url
     * @param string $to_url
     * @param int    $status_code
     *
     * @throws \Exception
     */
    public static function registerRedirect($storekeeper_id, $from_url, $to_url, $status_code)
    {
        self::databaseTableExistsCheck();

        global $wpdb;
        $table_name = self::getTableName();
        $wpdb->replace(
            $table_name,
            [
                'storekeeper_id' => $storekeeper_id,
                'from_url' => $from_url,
                'to_url' => $to_url,
                'status_code' => $status_code,
            ]
        );
    }

    public static function getRedirect($storekeeper_id)
    {
        self::databaseTableExistsCheck();

        global $wpdb;
        $table_name = self::getTableName();
        $sql = <<<SQL
	SELECT * 
	FROM $table_name
	WHERE storekeeper_id=%d 
SQL;

        return $wpdb->get_row($wpdb->prepare($sql, $storekeeper_id), ARRAY_A);
    }

    /**
     * @return bool|array
     */
    public static function checkRedirect($currentUrl)
    {
        if (!self::databaseTableExists()) {
            return null;
        }
        global $wpdb;
        $table_name = self::getTableName();
        $sql = <<<SQL
SELECT to_url, status_code 
FROM `$table_name` 
WHERE `from_url` = %s
SQL;

        $sqlPrepared = $wpdb->prepare($sql, $currentUrl);

        return $wpdb->get_row($sqlPrepared);
    }

    /**
     * Checks if there is a redirect at the current url.
     */
    public function redirect()
    {
        // Checks if there is a redirect at the current url
        $redirect = self::checkRedirect($_SERVER['REQUEST_URI']);
        if (null != $redirect) {
            // If there is a redirect, redirect to that url with given status code
            wp_redirect($redirect->to_url, $redirect->status_code);
            exit;
        }
    }
}
