<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use StoreKeeper\WooCommerce\B2C\Models\AttributeModel;

class WordpressCleaner
{
    public function cleanAll()
    {
        $this->cleanStoreKeeperIdFromPosts();
        $this->cleanStoreKeeperIdFromTerms();
        $this->cleanStoreKeeperIdFromUsers();
        $this->cleanStoreKeeperIdFromAttributes();
    }

    public function cleanStoreKeeperIdFromPosts()
    {
        global $wpdb;
        $tablename = $wpdb->postmeta;

        $sqlQuery = <<<SQL
    DELETE from `$tablename`
    WHERE `meta_key`="storekeeper_id"
SQL;

        $wpdb->query($sqlQuery);
    }

    public function cleanStoreKeeperIdFromTerms()
    {
        global $wpdb;
        $tablename = $wpdb->termmeta;

        $sqlQuery = <<<SQL
    DELETE from `$tablename`
    WHERE `meta_key`="storekeeper_id"
SQL;

        $wpdb->query($sqlQuery);
    }

    public function cleanStoreKeeperIdFromUsers()
    {
        global $wpdb;
        $tablename = $wpdb->usermeta;

        $sqlQuery = <<<SQL
    DELETE from `$tablename`
    WHERE `meta_key`="storekeeper_id"
SQL;

        $wpdb->query($sqlQuery);
    }

    public function cleanStoreKeeperIdFromAttributes()
    {
        AttributeModel::purge();
    }
}
