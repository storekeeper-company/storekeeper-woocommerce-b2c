<?php

namespace StoreKeeper\WooCommerce\B2C\Query;

use StoreKeeper\WooCommerce\B2C\Options\AbstractOptions;

class OptionQueryBuilder
{
    public static function getUpdateOptionSql(string $name, string $value): string
    {
        global $table_prefix;

        $name = AbstractOptions::getConstant($name);

        return <<<SQL
    UPDATE {$table_prefix}options
    SET option_value = '$value'
    WHERE option_name = '$name'
SQL;
    }

    public static function getInsertOptionSql(string $name, string $value): string
    {
        global $table_prefix;

        $name = AbstractOptions::getConstant($name);

        return <<<SQL
    INSERT INTO {$table_prefix}options
    (option_name, option_value)
    VALUES ('$name', '$value')
SQL;
    }

    public static function getCountOptionSql(string $name)
    {
        global $table_prefix;

        $name = AbstractOptions::getConstant($name);

        return <<<SQL
    SELECT COUNT(option_id) as count
    FROM {$table_prefix}options
    WHERE option_name = '$name'
SQL;
    }
}
