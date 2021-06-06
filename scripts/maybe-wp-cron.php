<?php
/**
 * Usually wp-cron.php is ran by Wordpress itself at every page load, that's why this feature is disabled for performance reasons.
 * Instead we run this script every minute using crontab with a 1/10 chance which makes sure the server will have a lower load.
 * Read more information here:
 * - https://medium.com/@thecpanelguy/the-nightmare-that-is-wpcron-php-ae31c1d3ae30.
 */
include_once __DIR__.'/../autoload.php';
\StoreKeeper\WooCommerce\B2C\Commands\CommandRunner::exitIFNotCli();

/**
 * First I calculate the 1 in 10 chance. for later to include the wp-cron.php if the number is 1.
 */
$chance = 10;
$number = mt_rand(1, $chance); // Calculates a 1 in 10 chance

/**
 * The path to the wp-cron.php location. this is in the root of the wordpress installation.
 * More information can be found here:
 * - https://codex.wordpress.org/Function_Reference/wp_cron.
 */
$wp_cron_location = __DIR__.'/../../../../wp-cron.php'; // The location of the wp-cron.php

/*
 * Checking the location of the wp-cron file.
 */
if (!file_exists($wp_cron_location)) {
    throw new Exception("Can not find wp-cron file @ $wp_cron_location");
}

/*
 * If the 1 in 10 chance number is 1. we will include the wp-cron file. which will cause to run it.
 * More information about this behaviour can be found here:
 * - http://php.net/manual/en/function.require-once.php
 */
if (1 === $number) {
    require_once $wp_cron_location;
}
