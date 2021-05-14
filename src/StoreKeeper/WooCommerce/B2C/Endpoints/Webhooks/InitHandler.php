<?php

namespace StoreKeeper\WooCommerce\B2C\Endpoints\Webhooks;

use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressCleaner;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressRestRequestWrapper;

class InitHandler
{
    /**
     * @var WordpressRestRequestWrapper
     */
    private $request;

    /**
     * InitHandler constructor.
     */
    public function __construct(WordpressRestRequestWrapper $request)
    {
        $this->request = $request;
    }

    public function run()
    {
        if (!StoreKeeperOptions::isConnected()) {
            $this->check();
            $this->save();

            return true;
        }

        return false;
    }

    private function check()
    {
        $has_old_api = StoreKeeperOptions::exists(StoreKeeperOptions::OLD_API_URL);
        $old_api = StoreKeeperOptions::get(StoreKeeperOptions::OLD_API_URL);
        $new_api = $this->request->getPayloadParam('api_url');

        if ($has_old_api && $old_api !== $new_api) {
            $wp_cleaner = new WordpressCleaner();
            $wp_cleaner->cleanAll();
        }
    }

    private function save()
    {
        StoreKeeperOptions::set(StoreKeeperOptions::API_URL, $this->request->getPayloadParam('api_url'));
        StoreKeeperOptions::set(StoreKeeperOptions::OLD_API_URL, $this->request->getPayloadParam('api_url'));
        StoreKeeperOptions::set(StoreKeeperOptions::GUEST_AUTH, $this->request->getPayloadParam('guest_auth'));
        StoreKeeperOptions::set(StoreKeeperOptions::SYNC_AUTH, $this->request->getPayloadParam('sync_auth'));
    }
}
