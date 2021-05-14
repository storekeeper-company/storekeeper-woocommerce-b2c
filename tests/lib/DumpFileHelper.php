<?php

namespace StoreKeeper\WooCommerce\B2C\TestLib;

use StoreKeeper\ApiWrapperDev\DumpFile\Reader;
use StoreKeeper\ApiWrapperDev\DumpFile\Writer;
use StoreKeeper\WooCommerce\B2C\Debug\HookDumpFile;

class DumpFileHelper
{
    public static function getReader(): Reader
    {
        $reader = new Reader();
        $reader->addExtraFileDumpType(HookDumpFile::HOOK_TYPE, HookDumpFile::class);

        return $reader;
    }

    public static function getWriter(): Writer
    {
        $writer = new Writer();
        $writer->addExtraFileDumpType(HookDumpFile::HOOK_TYPE, HookDumpFile::class);

        return $writer;
    }
}
