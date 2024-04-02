<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\FileExport;

use StoreKeeper\WooCommerce\B2C\Endpoints\AbstractEndpoint;
use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;
use StoreKeeper\WooCommerce\B2C\Interfaces\ProductExportInterface;
use StoreKeeper\WooCommerce\B2C\Interfaces\TagExportInterface;
use StoreKeeper\WooCommerce\B2C\Tools\IniHelper;

class ExportEndpoint extends AbstractEndpoint
{
    public const ROUTE = 'export';

    public function handle()
    {
        $type = $this->wrappedRequest->getRequest()->get_param('type');
        $lang = $this->wrappedRequest->getRequest()->get_param('lang');
        FileExportTypeHelper::ensureType($type);

        IniHelper::setIni(
            'max_execution_time',
            60 * 60 * 12, // Time in hours,
            [$this, 'logIniError']
        );

        IniHelper::setIni(
            'memory_limit',
            '512M',
            [$this, 'logIniError']
        );

        $exportClass = FileExportTypeHelper::getClass($type);
        $export = new $exportClass();

        if ($export instanceof ProductExportInterface) {
            $activeProductsOnly = 'true' === $this->wrappedRequest->getRequest()->get_param('activeProductsOnly');
            $export->setShouldExportActiveProductsOnly($activeProductsOnly);
        }

        if ($export instanceof TagExportInterface) {
            $skipEmptyTags = 'true' === $this->wrappedRequest->getRequest()->get_param('skipEmptyTags');
            $export->setShouldSkipEmptyTag($skipEmptyTags);
        }

        $export->runExport($lang);

        return [
            'url' => $export->getDownloadUrl(),
            'size' => $export->getSize(),
            'filename' => $export->getFilename(),
        ];
    }

    public function logIniError(string $message): void
    {
        $this->logger->error($message);
    }
}
