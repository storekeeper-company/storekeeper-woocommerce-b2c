<?php
/**
 * PHPUnit bootstrap file.
 */

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;

StoreKeeper\ApiWrapperDev\TestEnvLoader::loadDotEnv(__DIR__.'/../');

define('WP_TESTS', 1);

/**
 * Load the wp-config file, wordpress normally does this but we need it before wordpress starts.
 */
function _load_wp_config()
{
    // Getting the content of the wp-config file
    $wp_config = file(__DIR__.'../../../../../wp-config.php');

    // Getting the important lines of the wp-config file
    $out = ['<?php', ''];
    foreach ($wp_config as $line) {
        if (
            1 === preg_match('/^\s*(define)/', $line) &&
            false == strpos($line, 'ABSPATH')
        ) {
            $out[] = trim($line);
        }
    }

    // Creating a temp wp-config file and include it
    $wp_config_file = tempnam(sys_get_temp_dir(), 'wp-config').'.php';
    try {
        file_put_contents($wp_config_file, implode(PHP_EOL, $out));
        // need to define globals here to make sure it get's loaded in global scope
        @require $wp_config_file;
    } finally {
        unlink($wp_config_file);
    }
}

/**
 * Check if a connection to the database can be made.
 *
 * @return bool
 */
function _check_db()
{
    for ($tries_left = 12; $tries_left > 0; --$tries_left) {
        $connection = @new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

        if (!$connection) {
            sleep(5);
            continue;
        }

        // Check if the connection is alive
        if ($connection && $connection->connect_error) {
            // No connection, sadness, lets sleep for 5 seconds to make ourselves feel better
            echo 'Database connection could not be established yet, retrying in 5 seconds. Connection: '.DB_USER.'@'.DB_HOST.'/'.DB_NAME.PHP_EOL;
            sleep(5);
        } else {
            // Yay! Connection alive!
            $connection->close();

            return true;
        }
    }

    return false;
}

function _check_wp_ready()
{
    for ($tries_left = 24; $tries_left > 0; --$tries_left) {
        // Check if wordpress is installed correctly
        $output = [];
        exec('wp core is-installed 2>&1', $output);

        // Check if there was an error in the output, if so wordpress is not installed
        if (false !== strpos($output[0], 'Error') || !file_exists(__DIR__.'/../../woocommerce/woocommerce.php')) {
            // Wordpress is not ready yet, sadness, lets sleep for 5 seconds to make ourselves feel better
            echo 'Wordpress was not ready to start yet, retrying in 5 seconds.'.PHP_EOL;
            sleep(5);
        } else {
            return true;
        }
    }

    return false;
}

function _get_plugin_version()
{
    $regex = '/define\(\'STOREKEEPER_WOOCOMMERCE_B2C_VERSION\', \'(.*)\'\);/m';
    $content = file_get_contents(__DIR__.'/../storekeeper-woocommerce-b2c.php');
    preg_match($regex, $content, $matches);
    if (2 === count($matches)) {
        return $matches[1];
    }
    throw new Exception('Unable to find the STOREKEEPER_WOOCOMMERCE_B2C_VERSION in storekeeper-woocommerce-b2c.php');
}

function _check_plugin_ready()
{
    for ($tries_left = 24; $tries_left > 0; --$tries_left) {
        $option_key = escapeshellarg(StoreKeeperOptions::getConstant(StoreKeeperOptions::INSTALLED_VERSION));
        $output = [];
        exec("wp option get $option_key", $output);
        if (current($output) !== _get_plugin_version()) {
            echo 'The backoffice plugin was not activated yet, installing.'.PHP_EOL;

            echo 'Running: wp plugin activate woocommerce'.PHP_EOL;
            passthru('wp plugin activate woocommerce');
            echo 'Running: wp plugin activate storekeeper-for-woocommerce'.PHP_EOL;
            passthru('wp plugin activate storekeeper-for-woocommerce');

            sleep(1);
        } else {
            return true;
        }
    }

    return false;
}

_load_wp_config();

$connected = _check_db();
$wp_ready = _check_wp_ready();
$plugin_ready = _check_plugin_ready();

$env_copy_keys = ['WP_TESTS_DOMAIN', 'WP_TESTS_EMAIL', 'WP_TESTS_TITLE', 'WP_PHP_BINARY', 'WP_TESTS_CONFIG_FILE_PATH'];
foreach ($env_copy_keys as $env_key) {
    $env_var = getenv($env_key);
    if (!empty($env_var) && !defined($env_var)) {
        define($env_key, $env_var);
    }
}

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\').'/wordpress-tests-lib';
}

if (!file_exists($_tests_dir.'/includes/functions.php')) {
    echo "Could not find $_tests_dir/includes/functions.php, have you run bin/install-wp-tests.sh ?".PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    exit(1);
}

// Give access to tests_add_filter() function.
require_once $_tests_dir.'/includes/functions.php';

/**
 * Manually load required plugins.
 */
function _manually_wc_plugin()
{
    require __DIR__.'/../../woocommerce/woocommerce.php';
    require __DIR__.'/lib/WcHelper/include.php';
}

tests_add_filter('muplugins_loaded', '_manually_wc_plugin');

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin()
{
    require __DIR__.'/../storekeeper-woocommerce-b2c.php';
}

tests_add_filter('muplugins_loaded', '_manually_load_plugin');

if ($connected && $wp_ready && $plugin_ready) {
    // Start up the WP testing environment.
    require $_tests_dir.'/includes/bootstrap.php';
} else {
    if (!$connected) {
        echo "Could not connect to database. (database container probably didn't start fast enough)";
        exit(1);
    } else {
        if (!$wp_ready) {
            echo 'Wordpress did not start in 60 seconds, try to run `docker-compose -f docker-compose.test.yml up -d --build`. Then try to run the tests again.`';
            exit(1);
        } else {
            if (!$plugin_ready) {
                echo 'The plugin was not activated in 60 seconds, try to run `docker-compose -f docker-compose.test.yml up -d --build`. Then try to run the tests again.`';
                exit(1);
            }
        }
    }
}
