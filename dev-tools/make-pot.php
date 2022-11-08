<?php

// Generating pot file for this plugin
// to use please download http://develop.svn.wordpress.org/trunk/tools/i18n/ to ../../../../

if ('cli' == php_sapi_name()) {
    $base_dir = realpath(__DIR__);
    include_once $base_dir.'/i18n/makepot.php';

    $outputFile = '/tmp/storekeeper-woocommerce-b2c.pot';

    $makepot = new MakePOT();
    $makepot->wp_plugin(realpath($base_dir.'/../'), $outputFile);
    rename($outputFile, $base_dir.'/../i18n/storekeeper-woocommerce-b2c.pot');
} else {
    throw new \Exception('This script can only be run from command line');
}
