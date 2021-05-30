<?php

if ('cli' == php_sapi_name()) {
    // Checking if all variables are set
    if (empty($argv[1]) || empty($argv[2])) {
        $file = basename(__FILE__).'.php';
        throw new \Exception("Usage: php ./$file <version> <plugin_entry_path>");
    }

    // Setting variables
    $build = $argv[1];
    $file = (string) $argv[2];

    // Getting and manipulating lines
    $lines = file_get_contents($file);
    $lines = preg_replace(
        '/^(.*define.*STOREKEEPER_WOOCOMMERCE_B2C_VERSION.*\')([\d\.]+)(\'.*)$/m',
        '${1}'.$build.'$3',
        $lines
    );
    $lines = preg_replace('/^([\s\*]*Version:\s*)([\d\.]+)\s*$/m', '${1}'.$build, $lines);
    $lines = preg_replace('/^([\s\*]*Stable Tag:\s*)([\d\.]+)\s*$/m', '${1}'.$build, $lines);

    if (empty($lines)) {
        throw new \Exception('Plugin entry file is empty is empty');
    }

    // Updating file
    file_put_contents($file, $lines);
} else {
    throw new \Exception('This script can only be run from command line');
}
