<?php

namespace StoreKeeper\WooCommerce\B2C\Exports;

use StoreKeeper\ApiWrapper\ApiWrapper;
use StoreKeeper\ApiWrapper\Exception\AuthException;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Exceptions\PluginDisconnectedException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;

abstract class AbstractExport
{
    /**
     * @var null
     */
    protected $id;

    /**
     * @var ApiWrapper
     */
    protected $storekeeper_api;

    /**
     * @var int
     */
    protected $storekeeper_id;
    protected bool $debug;

    /**
     * AbstractExport constructor.
     *
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        $this->id = key_exists('id', $settings) ? (int) $settings['id'] : 0;
        $this->storekeeper_api = StoreKeeperApi::getApiByAuthName(StoreKeeperApi::SYNC_AUTH_DATA);
        $this->debug = key_exists('debug', $settings) ? (bool) $settings['debug'] : false;
    }

    protected function getMainLanguage()
    {
        return Language::getSiteLanguageIso2();
    }

    abstract protected function getFunction();

    abstract protected function getMetaFunction();

    abstract protected function getFunctionMultiple();

    abstract protected function getArguments();

    protected function getStoreKeeperIdFromMeta()
    {
        $function = $this->getMetaFunction();

        return $function($this->id, 'storekeeper_id', true);
    }

    protected function debug($message = 'Memory usage', $data = [])
    {
        if ($this->debug) {
            echo esc_html('['.date('Y-m-d H:i:s').'] '.$message.' '.json_encode($data).PHP_EOL);
            echo sprintf(
                'Memory usage: %.2f MB, peak: %.2f MB.',
                memory_get_usage() / 1024 / 1024,
                memory_get_peak_usage() / 1024 / 1024
            ).PHP_EOL.PHP_EOL;
        }
    }

    protected function isSingle()
    {
        return !is_null($this->id);
    }

    protected function already_exported()
    {
        return $this->get_storekeeper_id() > 0;
    }

    protected function get_storekeeper_id()
    {
        if (is_null($this->storekeeper_id)) {
            $this->storekeeper_id = $this->getStoreKeeperIdFromMeta();
        }

        return $this->storekeeper_id;
    }

    public function run(): void
    {
        $this->debug('Started export');

        try {
            if ($this->isSingle()) {
                $function = $this->getFunction();
                $this->debug('[single] Export for function: '.$this->getFunction());

                $wpObject = new $function($this->id);
                $this->debug('Found object', $wpObject);
                $this->processItem($wpObject);
            } else {
                $this->debug('Found multiple');
                $function = $this->getFunctionMultiple();
                $this->debug('[multiple] Export for function: '.$this->getFunctionMultiple());
                $arguments = $this->getArguments();
                $arguments['number'] = key_exists('number', $arguments) ? $arguments['number'] : -1;
                $this->debug('[multiple] Export function arguments: '.$arguments);
                if (!function_exists($function)) {
                    throw new \Exception("Function '$function' does not exists");
                }
                $wpArray = $function($arguments);
                foreach ($wpArray as $index => $wpObject) {
                    $this->debug('['.count($wpArray).'/'.$index.'] [multiple] Export object: '.$wpObject);
                    $this->processItem($wpObject);
                }
            }
        } catch (AuthException $e) {
            throw $this->buildPluginDisconnectedException($e);
        } catch (GeneralException $e) {
            throw $this->convertKnownGeneralException($e);
        }
    }

    protected function convertKnownGeneralException(GeneralException $throwable): \Throwable
    {
        return $throwable;
    }

    abstract protected function processItem($order): void;

    protected function buildPluginDisconnectedException($e): PluginDisconnectedException
    {
        return new PluginDisconnectedException(
            esc_html__('This channel was disconnected in StoreKeeper Backoffice, please reconnect it manually.', I18N::DOMAIN),
            0,
            $e
        );
    }
}
