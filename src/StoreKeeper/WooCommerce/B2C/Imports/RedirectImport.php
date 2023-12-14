<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\RedirectHandler;

class RedirectImport extends AbstractImport
{
    /**
     * @var int the storekeeper id
     */
    private $storekeeper_id;

    /**
     * AttributeOptionImport constructor.
     *
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        $this->storekeeper_id = key_exists('storekeeper_id', $settings) ? (int) $settings['storekeeper_id'] : 0;
        unset($settings['storekeeper_id'], $settings['attribute_id'], $settings['attribute_option_ids']);
        parent::__construct($settings);
    }

    /**
     * @return string
     */
    protected function getModule()
    {
        return 'ShopModule';
    }

    /**
     * @return string
     */
    protected function getFunction()
    {
        return 'listSiteRedirectsForHooks';
    }

    /**
     * @return null
     */
    protected function getLanguage()
    {
        return null;
    }

    /**
     * @return array
     */
    protected function getFilters()
    {
        $filters = [];
        if ($this->storekeeper_id > 0) {
            $filters[] = [
                'name' => 'id__=',
                'val' => $this->storekeeper_id,
            ];
        }

        return $filters;
    }

    /**
     * @param Dot $dotObject
     *
     * @throws \Exception
     */
    protected function processItem($dotObject, array $options = []): ?int
    {
        // Get the needed parameters from the deep object item
        $to_url = $dotObject->get('to_url');
        $from_url = $dotObject->get('from_url');
        $status_code = $dotObject->get('http_status_code');

        $this->debug("Importing redirect from $from_url to $to_url");

        // Register the redirect in the redirect database
        RedirectHandler::registerRedirect($this->storekeeper_id, $from_url, $to_url, $status_code);

        $this->debug("Imported redirect from $from_url to $to_url");

        return null;
    }

    protected function getImportEntityName(): string
    {
        return __('redirect', I18N::DOMAIN);
    }
}
