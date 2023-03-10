<?php

namespace StoreKeeper\WooCommerce\B2C\Query;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Options\WooCommerceOptions;

class CronQueryBuilder
{
    /*
     * Last ran
     */
    public static function getUpdateLastRunTimeSql(): string
    {
        return OptionQueryBuilder::getUpdateOptionSql(
            WooCommerceOptions::LAST_SYNC_RUN,
            DatabaseConnection::formatToDatabaseDate()
        );
    }

    public static function getInsertLastRunTimeSql(): string
    {
        return OptionQueryBuilder::getInsertOptionSql(
            WooCommerceOptions::LAST_SYNC_RUN,
            DatabaseConnection::formatToDatabaseDate()
        );
    }

    public static function getCountLastRunTimeSql(): string
    {
        return OptionQueryBuilder::getCountOptionSql(
            WooCommerceOptions::LAST_SYNC_RUN
        );
    }

    /*
     * Last ran
     */
    public static function getUpdateSuccessRunTimeSql(): string
    {
        return OptionQueryBuilder::getUpdateOptionSql(
            WooCommerceOptions::SUCCESS_SYNC_RUN,
            DatabaseConnection::formatToDatabaseDate()
        );
    }

    public static function getInsertSuccessRunTimeSql(): string
    {
        return OptionQueryBuilder::getInsertOptionSql(
            WooCommerceOptions::SUCCESS_SYNC_RUN,
            DatabaseConnection::formatToDatabaseDate()
        );
    }

    public static function getCountSuccessRunTimeSql(): string
    {
        return OptionQueryBuilder::getCountOptionSql(
            WooCommerceOptions::SUCCESS_SYNC_RUN
        );
    }

    public static function getCronOptionSql(): string
    {
        global $table_prefix;

        return <<<SQL
    SELECT option_value 
    FROM `{$table_prefix}options` 
    WHERE option_name='cron'
SQL;
    }

    public static function updateCronOptionSql(DatabaseConnection $db, array $data): string
    {
        global $table_prefix;

        $value = $db->escapeSQLString(serialize($data));

        return <<<SQL
	UPDATE `{$table_prefix}options` 
    SET option_value='$value'
	WHERE option_name='cron'
SQL;
    }
}
