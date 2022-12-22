<?php

include_once __DIR__.'/autoload.php';

$lock = new \StoreKeeper\WooCommerce\B2C\Tasks\BackrefImportLock('aaa');
$lock->lock();
echo 'Locked (sleep 60s)';
sleep(60);
echo 'released lock';
