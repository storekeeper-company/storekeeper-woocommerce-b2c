<?php

namespace Tests;

use Monolog\Logger;
use StoreKeeper\ApiWrapperDev\DumpFile;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\TestLib\DumpFileHelper;

include_once __DIR__.'/../autoload.php';

// execute the script
main();

function main()
{
    $logger = getLogger();
    $realpath = findPath();

    $is_dir = is_dir($realpath);
    $logger->info(
        'Path found',
        [
            'path' => $realpath,
            'is_dir' => $is_dir,
        ]
    );

    $reader = DumpFileHelper::getReader();
    if ($is_dir) {
        $dir = $realpath.'/';
        $files = getJsonFilesFromDir($dir);

        $logger->info(
            'Found files ',
            [
                'path' => $realpath,
                'count' => count($files),
            ]
        );
        foreach ($files as $filename) {
            rewriteFile($reader, $dir, $filename, $logger);
        }
    } else {
        $filename = basename($realpath);
        $dir = dirname($realpath);
        rewriteFile($reader, $dir, $filename, $logger);
    }
}

/**
 * @throws \Exception
 */
function getLogger(): Logger
{
    $options = getopt('vvv');
    $count = empty($options['v']) ? 0 : count($options['v']);
    if ($count > 3) {
        $count = 3;
    }
    $levels = [
        0 => 'WARNING',
        1 => 'NOTICE',
        2 => 'INFO',
        3 => 'DEBUG',
    ];
    $_ENV['STOREKEEPER_WOOCOMMERCE_B2C_LOG_LEVEL'] = $levels[$count];
    $logger = LoggerFactory::createConsole(basename(__FILE__));

    return $logger;
}

/**
 * @return false|string
 *
 * @throws \Exception
 */
function findPath()
{
    global $argv, $argc;
    $path = null;
    for ($i = 1; $i < $argc; ++$i) {
        if (0 !== strpos($argv[$i], '-')) {
            $path = $argv[$i];
        }
    }
    // find the path
    if (empty($path)) {
        throw new \Exception('First argument should be the path to data');
    }
    $realpath = realpath($path);
    if (empty($realpath)) {
        $realpath = realpath(__DIR__.'/'.$path);
    }
    if (empty($realpath)) {
        throw new \Exception("Cannot find the $path");
    }

    return $realpath;
}

function getJsonFilesFromDir(string $realpath): array
{
    $files = scandir($realpath);

    return array_filter(
        $files,
        function ($v) use ($realpath) {
            return is_file($realpath.'/'.$v)
                && strpos($v, '.json') === strlen($v) - 5;
        }
    );
}

/**
 * @throws \Exception
 */
function rewriteFile(DumpFile\Reader $reader, string $dir, $filename, Logger $logger): void
{
    $file = $reader->read($dir.$filename);
    $type = $file->getType();
    if (DumpFile::MODULE_TYPE === $type) {
        $data = $file->getData();
        $hash = DumpFile::calculateDataHash($data['params'] ?? null);

        $new_filename = $type.'.';
        $new_filename .= $file->getModuleName().'::'.$file->getModuleFunction().'.';
        $new_filename .= "$hash.json";

        $json = DumpFile\Writer::encode($data);
        if (!file_put_contents($dir.$new_filename, $json)) {
            throw new \Exception("Failed to write to $dir.$new_filename");
        }

        $logger->warning(
            'Created file',
            [
                'old_file' => $filename,
                'new_filename' => $new_filename,
            ]
        );
    } else {
        $logger->warning(
            'Skipped file, because not handled type',
            [
                'file' => $filename,
                'type' => $type,
            ]
        );
    }
}
