<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\FileExport;

use StoreKeeper\WooCommerce\B2C\Endpoints\AbstractEndpoint;
use StoreKeeper\WooCommerce\B2C\Exceptions\FileExportFailedException;
use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\Tools\IniHelper;

class ExportEndpoint extends AbstractEndpoint
{
    const ROUTE = 'export';

    public function handle()
    {
        $type = $this->wrappedRequest->getRequest()->get_param('type');
        $lang = $this->wrappedRequest->getRequest()->get_param('lang');
        FileExportTypeHelper::ensureType($type);

        IniHelper::setIni(
            'max_execution_time',
            60 * 60 * 12, // Time in hours,
            [$this, 'throwIniError']
        );

        IniHelper::setIni(
            'memory_limit',
            '512M',
            [$this, 'throwIniError']
        );

        $exportClass = FileExportTypeHelper::getClass($type);
        $export = new $exportClass();
        $export->runExport($lang);

        return [
            'url' => $export->getDownloadUrl(),
            'size' => $export->getSize(),
            'filename' => $export->getFilename(),
        ];
    }

    /**
     * @throws FileExportFailedException
     */
    public function throwIniError(string $message): void
    {
        throw new FileExportFailedException($message);
    }
}
