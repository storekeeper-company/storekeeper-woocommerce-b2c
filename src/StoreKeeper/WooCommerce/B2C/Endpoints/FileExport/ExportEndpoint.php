<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\FileExport;

use StoreKeeper\WooCommerce\B2C\Endpoints\AbstractEndpoint;
use StoreKeeper\WooCommerce\B2C\Helpers\FileExportTypeHelper;

class ExportEndpoint extends AbstractEndpoint
{
    const ROUTE = 'export';

    public function handle()
    {
        $type = $this->wrappedRequest->getRequest()->get_param('type');
        $lang = $this->wrappedRequest->getRequest()->get_param('lang');
        FileExportTypeHelper::ensureType($type);

        $exportClass = FileExportTypeHelper::getClass($type);
        $export = new $exportClass();
        $export->runExport($lang);

        return $export->getDownloadUrl();
    }
}
