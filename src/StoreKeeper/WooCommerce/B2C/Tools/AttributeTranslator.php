<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

/**
 * Class AttributeTranslator.
 *
 * This class is used to set translate a Backoffice attribute to a WooCommerce attribute. This is needed because there
 * are certain attribute names, which are reserved within WooCommerce and cannot be used. A full list of these reserved
 * names can be found here:
 * https://docs.woocommerce.com/wc-apidocs/source-function-wc_check_if_attribute_name_is_reserved.html#213-301
 */
class AttributeTranslator
{
    // This is the table name that will be added to the database to keep track of all the translations
    const DATABASE_TABLE_NAME = 'storekeeper_woocommerce_attribute_translation';

    //region Database setup

    /**
     * Retrieve the table name that is being used to store the translations.
     *
     * @return string
     */
    public static function getTableName()
    {
        global $wpdb;

        return $wpdb->prefix.self::DATABASE_TABLE_NAME;
    }

    /**
     * Check whether the database table exists already.
     *
     * @return bool
     */
    public static function databaseTableExists()
    {
        global $wpdb;
        $table_name = self::getTableName();

        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }

    /**
     * Check whether the database table exists already. Tries to create it if it doesn't exists, and thrown an exception
     * when it still doesn't exist after trying to create it.
     *
     * @throws \Exception
     */
    private static function databaseTableExistsCheck()
    {
        self::createTable();
        if (!self::databaseTableExists()) {
            $table_name = self::getTableName();
            throw new \Exception("Database table '$table_name' does not exist");
        }
    }

    /**
     * Create the table in the database. This function will setup the table and its indices.
     */
    public static function createTable()
    {
        global $wpdb;

        // Only continue if the database table exist
        if (!self::databaseTableExists()) {
            require_once ABSPATH.'wp-admin/includes/upgrade.php';
            $table_name = self::getTableName();

            // Creation of the table
            $sqlCreate =
                <<<SQL
CREATE TABLE `$table_name` (
  `translation_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `attribute_storekeeper` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
  `attribute_woocommerce` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
  PRIMARY KEY (`translation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
            if (false === $wpdb->query($sqlCreate)) {
                throw new \Exception($wpdb->last_error);
            }

            self::setVersion('1.0');
        }
    }

    //endregion

    //region Version

    /**
     * Set the version of the database table.
     *
     * @param $version
     */
    private static function setVersion($version)
    {
        update_option(self::DATABASE_TABLE_NAME.'_version', $version);
    }

    /**
     * Get the version of the database table.
     *
     * @return mixed|void
     */
    public static function getVersion()
    {
        return get_option(self::DATABASE_TABLE_NAME.'_version');
    }

    //endregion

    //region Translation

    /**
     * Create a new translation when needed based on the passed attribute slug. When a already exists, it will retrieve
     * the correct slug and returns it. When a slug doesn't need translating, the same value will be returned, otherwise
     * the translated slug will be returned.
     *
     * @param $backoffice_attribute_slug
     *
     * @return string
     *
     * @throws \Exception
     */
    public static function setTranslation($backoffice_attribute_slug)
    {
        // Make sure the database table exists
        self::databaseTableExistsCheck();

        // Only create a new translation is there ins't one already. When a translation exists, return it.
        if (self::hasTranslation($backoffice_attribute_slug)) {
            return self::getTranslation($backoffice_attribute_slug);
        }

        // When the attribute slug is empty, generate a random string and return it
        if (empty($backoffice_attribute_slug)) {
            $woocommerce_attribute_slug = StringFunctions::generateRandomString();
            self::insertTranslationIntoDatabase($backoffice_attribute_slug, $woocommerce_attribute_slug);

            return $woocommerce_attribute_slug;
        }

        $woocommerce_attribute_slug = $backoffice_attribute_slug;

        $validatedSlug = self::validateAttribute($woocommerce_attribute_slug);
        if ($validatedSlug !== $woocommerce_attribute_slug) {
            $woocommerce_attribute_slug = $validatedSlug;
            // Store the translation into the database
            self::insertTranslationIntoDatabase($backoffice_attribute_slug, $woocommerce_attribute_slug);
        }

        // Return the attribute slug (either the translated one, or the same of if no translation was needed
        return $woocommerce_attribute_slug;
    }

    /**
     * Insert the translation into the database.
     *
     * @param $backoffice_attribute_slug
     * @param $woocommerce_attribute_slug
     */
    private static function insertTranslationIntoDatabase($backoffice_attribute_slug, $woocommerce_attribute_slug)
    {
        $table_name = self::getTableName();

        global $wpdb;
        $inserted = $wpdb->insert(
            $table_name,
            [
                'attribute_storekeeper' => $backoffice_attribute_slug,
                'attribute_woocommerce' => $woocommerce_attribute_slug,
            ]
        );

        // There was an error while inserting
        if (false === $inserted) {
            throw new \Exception($wpdb->last_error);
        }
    }

    /**
     * Check whether a translation already exists for the passed attribute slug.
     *
     * @param $backoffice_attribute_slug
     *
     * @return bool
     *
     * @throws \Exception
     */
    private static function hasTranslation($backoffice_attribute_slug)
    {
        self::databaseTableExistsCheck();

        $table_name = self::getTableName();
        global $wpdb;

        $sql =
            <<<SQL
SELECT COUNT(attribute_storekeeper)
FROM `$table_name`
WHERE `attribute_storekeeper` = %s;
SQL;

        $sql_prepared = $wpdb->prepare($sql, $backoffice_attribute_slug);
        $count = current($wpdb->get_row($sql_prepared));

        if ($count >= 1) {
            return true;
        } else {
            return false;
        }
    }

    private static function hasTranslatedWoocommerceSlug($woocommerce_attribute_slug)
    {
        self::databaseTableExistsCheck();
        if (!empty($woocommerce_attribute_slug)) {
            $table_name = self::getTableName();
            global $wpdb;

            $sql =
                <<<SQL
SELECT COUNT(attribute_woocommerce)
FROM `$table_name`
WHERE `attribute_woocommerce` = %s;
SQL;

            $sql_prepared = $wpdb->prepare($sql, $woocommerce_attribute_slug);
            $count = current($wpdb->get_row($sql_prepared));

            return $count >= 1;
        }
    }

    /**
     * Get the translated value based on the passed attribute slug.
     *
     * @param $backoffice_attribute_slug
     *
     * @return string
     *
     * @throws \Exception
     */
    public static function getTranslation($backoffice_attribute_slug)
    {
        self::databaseTableExistsCheck();
        if (!empty($backoffice_attribute_slug)) {
            $table_name = self::getTableName();
            global $wpdb;

            $sql =
                <<<SQL
SELECT attribute_woocommerce
FROM `$table_name`
WHERE `attribute_storekeeper` = %s;
SQL;

            $sql_prepared = $wpdb->prepare($sql, $backoffice_attribute_slug);
            $translated_attribute_slug = current($wpdb->get_row($sql_prepared));

            if (empty($translated_attribute_slug)) {
                return $backoffice_attribute_slug;
            } else {
                return $translated_attribute_slug;
            }
        } else {
            throw new \Exception('Attribute cannot be empty');
        }
    }

    /**
     * @return string
     */
    public static function validateAttribute(string $woocommerce_attribute_slug)
    {
        if (wc_check_if_attribute_name_is_reserved($woocommerce_attribute_slug) ||
            strlen($woocommerce_attribute_slug) >= 28) {
            // Custom attribute slugs are prefixed with ‘pa_’, so an attribute called ‘size’ would be ‘pa_size’
            if (!StringFunctions::startsWith($woocommerce_attribute_slug, 'pa_')) {
                $woocommerce_attribute_slug = wc_attribute_taxonomy_name($woocommerce_attribute_slug);
            }

            // Loop to check for a free taxonomy
            $woocommerce_attribute_slug_base = substr($woocommerce_attribute_slug, 0, 23);
            $correct_taxonomy = false;
            while (!$correct_taxonomy) {
                // Append _[0-9a-z]{4}
                $woocommerce_attribute_slug = $woocommerce_attribute_slug_base.'_'.StringFunctions::generateRandomString(4);

                if (!self::hasTranslatedWoocommerceSlug($woocommerce_attribute_slug)) {
                    $correct_taxonomy = true;
                }
            }

            // Append _[0-9a-z]{4}
            return $woocommerce_attribute_slug.'_'.
                StringFunctions::generateRandomString(4);
        }

        return $woocommerce_attribute_slug;
    }

    //endregion
}
