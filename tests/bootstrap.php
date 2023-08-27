<?php
/**
 * PHPUnit bootstrap file.
 */

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

$wp_tests_dir = getenv('WORPRESS_DEV_TEST_DIR');
if( empty($wp_tests_dir)){
    throw new Exception("No WORPRESS_DEV_TEST_DIR env variable is set");
}

$wp_bootstrap = $wp_tests_dir.'/phpunit/includes/bootstrap.php';
if (!file_exists($wp_bootstrap)) {
    echo "Could not find $wp_bootstrap".PHP_EOL;
    exit(1);
}

require_once $wp_bootstrap;

require_once __DIR__.'/../autoload.php';
StoreKeeper\ApiWrapperDev\TestEnvLoader::loadDotEnv(__DIR__.'/../');
