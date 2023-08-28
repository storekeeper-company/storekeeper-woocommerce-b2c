<?php

$wp_tests_dir = getenv('WORPRESS_DEV_TEST_DIR');
if (empty($wp_tests_dir)) {
    throw new Exception('No WORPRESS_DEV_TEST_DIR env variable is set');
}

$wp_dev_dir = getenv('WORPRESS_DEV_DIR');
if (empty($wp_dev_dir)) {
    throw new Exception('No WORPRESS_DEV_DIR env variable is set');
}

require_once $wp_dev_dir.'/vendor/autoload.php';
require_once $wp_tests_dir.'/phpunit/includes/bootstrap.php';
require_once __DIR__.'/../autoload.php';

StoreKeeper\ApiWrapperDev\TestEnvLoader::loadDotEnv(__DIR__.'/../');
