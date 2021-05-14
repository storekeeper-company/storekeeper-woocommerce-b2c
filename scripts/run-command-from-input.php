<?php

// used primary in CommandRunner::executeFromInputJson
// can be used as standalone command, if correctly formatted json is passed as input
include_once __DIR__.'/../autoload.php';

use StoreKeeper\WooCommerce\B2C\Commands\CommandRunner;
use StoreKeeper\WooCommerce\B2C\Core;

// no need to even load wp, if not cli
CommandRunner::exitIFNotCli();

// run the command
$runner = Core::getCommandRunner();
$runner->setConsoleLogger();
if (!empty($command_contents)) {
    $exit = $runner->executeFromInputJson($command_contents);
} else {
    $exit = $runner->executeFromInputJson();
}
exit($exit);
