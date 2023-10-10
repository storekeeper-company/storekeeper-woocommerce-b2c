<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use StoreKeeper\ApiWrapper\ApiWrapper;
use StoreKeeper\WooCommerce\B2C\Database\ImportProcessLock;
use StoreKeeper\WooCommerce\B2C\Exceptions\LockException;
use StoreKeeper\WooCommerce\B2C\Exceptions\LockTimeoutException;
use StoreKeeper\WooCommerce\B2C\Exceptions\ProductImportException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\Helpers\WpCliHelper;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Interfaces\LockInterface;
use StoreKeeper\WooCommerce\B2C\Interfaces\WithConsoleProgressBarInterface;
use StoreKeeper\WooCommerce\B2C\Tools\Language;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use StoreKeeper\WooCommerce\B2C\Traits\TaskHandlerTrait;

abstract class AbstractImport
{
    use LoggerAwareTrait;
    use TaskHandlerTrait;

    const EDIT_CONTEXT = 'edit';

    /**
     * @var int
     */
    protected $items_per_fetch = 100;

    /**
     * @var int
     */
    protected $import_limit = 0;

    /**
     * @var int
     */
    protected $import_start = 0;

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var ApiWrapper
     */
    protected $storekeeper_api = null;

    protected $processedItemCount = 0;

    protected int $failedItemCount = 0;

    protected $isProgressBarShown = true;

    /**
     * @var LockInterface
     */
    protected $lock;


    public function setIsProgressBarShown(bool $isProgressBarShown): void
    {
        $this->isProgressBarShown = $isProgressBarShown;
    }

    protected function shouldShowProgressBar(): bool
    {
        return $this->isProgressBarShown && $this instanceof WithConsoleProgressBarInterface;
    }

    /**
     * AbstractImport constructor.
     *
     * @throws Exception
     */
    public function __construct(array $settings = [])
    {
        $this->setLogger(new NullLogger());
        $this->storekeeper_api = StoreKeeperApi::getApiByAuthName(StoreKeeperApi::SYNC_AUTH_DATA);
        $this->import_start = key_exists('start', $settings) ? (int) $settings['start'] : 0;
        $this->import_limit = key_exists('limit', $settings) ? (int) $settings['limit'] : 0;
        $this->debug = key_exists('debug', $settings) ? (bool) $settings['debug'] : false;
    }

    /**
     * @param $message
     * @param array $data
     */
    protected function debug($message, $data = [])
    {
        if (!is_array($data)) {
            $data = [$data];
        }
        $this->logger->debug($message, $data);
    }

    public static function getMainLanguage()
    {
        return Language::getSiteLanguageIso2();
    }

    protected function getQuery()
    {
        return null;
    }

    protected function getLanguage()
    {
        return Language::getSiteLanguageIso2();
    }

    /**
     * Apply filters which will be used on the getFunction.
     *
     * @return array
     */
    protected function getFilters()
    {
        return [];
    }

    protected function getSorts()
    {
        return [
            [
                'name' => 'id',
                'dir' => 'asc',
            ],
        ];
    }

    public function getFailedItemCount(): int
    {
        return $this->failedItemCount;
    }


    public function total_still_left($offset)
    {
        $module = $this->storekeeper_api->getModule($this->getModule());
        $function = $this->getFunction();
        $query = $this->getQuery();
        $language = $this->getLanguage();
        $filters = $this->getFilters();
        $sorts = $this->getSorts();
        $start = $this->import_start + (int) $offset;

        // Only call for one
        $limit = 1;

        // Call
        $response = [];
        if (is_null($query) && is_null($language)) {
            $response = $module->$function($start, $limit, $sorts, $filters);
        } else {
            if (is_null($query) && !is_null($language)) {
                $response = $module->$function($language, $start, $limit, $sorts, $filters);
            } else {
                if (!is_null($query) && !is_null($language)) {
                    $response = $module->$function($query, $language, $start, $limit, $sorts, $filters);
                } else {
                    if (!is_null($query) && is_null($language)) {
                        $response = $module->$function($query, $start, $limit, $sorts, $filters);
                    }
                }
            }
        }

        $fetched_total = (int) $response['total'];

        return $fetched_total - $start;
    }

    protected $total_fetched = 0;

    /**
     * @throws LockTimeoutException
     * @throws LockException
     */
    public function lock(): bool
    {
        $this->lock = new ImportProcessLock(static::class);

        return $this->lock->lock();
    }

    /**
     * @throws LockTimeoutException
     * @throws LockException
     */
    public function run(array $options = []): void
    {
        try {
            $this->lock();
        } catch (LockTimeoutException|LockException $exception) {
            $this->logger->error('Cannot run. lock on.');
            throw $exception;
        }

        $this->debug('Started a run!');

        $runStartTime = date('redis');
        $productImportErrorLogger = null;

        $moduleName = $this->getModule();
        $module = $this->storekeeper_api->getModule($moduleName);
        $functionName = $this->getFunction();
        $query = $this->getQuery();
        $language = $this->getLanguage();
        $filters = $this->getFilters();
        $sorts = $this->getSorts();
        $start = $this->import_start;
        $fetched_total = 0;
        $fetch_max = $this->import_limit;
        $done_importing = false;

        $this->debug("Started $moduleName::$functionName");

        $limit = $this->items_per_fetch;
        if (0 !== $this->import_limit && $this->items_per_fetch > $this->import_limit) {
            $limit = $this->import_limit;
        }
        $last_fetched_amount = $limit; // For start should be equal to the limit

        while (!$done_importing && $last_fetched_amount >= $limit) {
            $response = [];
            if (is_null($query) && is_null($language)) {
                $this->debug(
                    "fetching $moduleName::$functionName with:",
                    ['start' => $start, 'limit' => $limit, 'sorts' => $sorts, 'filters' => $filters]
                );
                $response = $module->$functionName($start, $limit, $sorts, $filters);
            } else {
                if (is_null($query) && !is_null($language)) {
                    $this->debug(
                        "fetching $moduleName::$functionName with:",
                        [
                            'language' => $language,
                            'start' => $start,
                            'limit' => $limit,
                            'sorts' => $sorts,
                            'filters' => $filters,
                        ]
                    );
                    $response = $module->$functionName($language, $start, $limit, $sorts, $filters);
                } else {
                    if (!is_null($query) && !is_null($language)) {
                        $this->debug(
                            "fetching $moduleName::$functionName with:",
                            [
                                'query' => $query,
                                'language' => $language,
                                'start' => $start,
                                'limit' => $limit,
                                'sorts' => $sorts,
                                'filters' => $filters,
                            ]
                        );
                        $response = $module->$functionName($query, $language, $start, $limit, $sorts, $filters);
                    } else {
                        if (!is_null($query) && is_null($language)) {
                            $this->debug(
                                "fetching $moduleName::$functionName with:",
                                [
                                    'query' => $query,
                                    'start' => $start,
                                    'limit' => $limit,
                                    'sorts' => $sorts,
                                    'filters' => $filters,
                                ]
                            );
                            $response = $module->$functionName($query, $start, $limit, $sorts, $filters);
                        }
                    }
                }
            }

            if (key_exists('count', $response)) {
                $this->debug("$moduleName::$functionName fetched {$response['count']} items");
            } else {
                $this->debug("$moduleName::$functionName did not fetched any items");
            }

            if (key_exists('data', $response) && key_exists('count', $response)) {
                $last_fetched_amount = (int) $response['count'];
                $start = $start + ($last_fetched_amount - 1);
                $items = $response['data'];
                if ($this->shouldShowProgressBar()) {
                    $this->createProgressBar(
                        $last_fetched_amount,
                        WpCliHelper::setGreenOutputColor(sprintf(
                            __('Syncing %s from Storekeeper backoffice', I18N::DOMAIN),
                            $this->getImportEntityName()
                        )));
                }
                foreach ($items as $index => $item) {
                    ++$this->total_fetched;
                    $count = $index + 1;
                    try {
                        $dotObject = new Dot($item);
                        $this->processItem($dotObject, $options);

                        unset($dotObject);

                        ++$this->processedItemCount;
                        $this->debug("Processed {$count}/{$response['count']} items");
                    } catch (ProductImportException $exception) {
                        $errorMessage = $exception->getMessage();

                        $data = [
                            'item' => $item,
                            'exception' => $exception,
                        ];

                        $this->logger->error(
                            "A product failed to import with exception: {$errorMessage}",
                            $data
                        );

                        if (is_null($productImportErrorLogger)) {
                            $productImportErrorLogger = LoggerFactory::create("product-import-error-{$runStartTime}");
                        }

                        $productImportErrorLogger->error('Failed to import product', [
                            'shop_product_id' => $exception->getShopProductId(),
                            'error' => $errorMessage
                        ]);

                        ++$this->failedItemCount;
                        ++$this->processedItemCount;
                        $this->debug("Processed {$count}/{$response['count']} items");
                    } catch (\Throwable $exception) {
                        $errorMessage = $exception->getMessage();
                        $data = [
                            'item' => $item,
                            'exception' => $exception,
                        ];

                        $this->logger->error(
                            "Failed to process item({$count}/{$response['count']}) with exception: {$errorMessage}",
                            $data
                        );
                        throw $exception;
                    } finally {
                        if ($this->shouldShowProgressBar()) {
                            $this->tickProgressBar();
                        }
                    }
                }
                unset($items);
                unset($response);

                if ($this->shouldShowProgressBar()) {
                    $this->endProgressBar();
                }
            }

            $fetched_total = $fetched_total + $limit; // always up it with the limit

            if (0 !== $fetch_max && $fetched_total >= $fetch_max) {
                $done_importing = true;
                $this->debug("Done importing $fetched_total items.");
            }
        }

        $this->debug('started after run', $fetched_total);

        $this->afterRun();
    }

    public function getTranslationIfRequired(Dot $deepArray, $path, $fallback = null)
    {
        $currentLanguage = $this->getLanguage();
        $backendLanguage = $deepArray->get('translatable.lang');

        if ($currentLanguage === $backendLanguage) {
            return $deepArray->get($path, $fallback);
        }
        $untranslated = $deepArray->get($path, $fallback);

        return $deepArray->get("translation.$path", $untranslated);
    }

    public function getProcessedItemCount(): int
    {
        return $this->processedItemCount;
    }

    /**
     * @param $dotObject Dot
     * @param $options
     */
    abstract protected function processItem(Dot $dotObject, array $options = []);

    abstract protected function getImportEntityName(): string;

    /**
     * Which module to call.
     *
     * @return string
     */
    protected function getModule()
    {
    }

    /**
     * Which function to call on the module.
     *
     * @return string
     */
    protected function getFunction()
    {
    }

    protected function afterRun()
    {
        WpCliHelper::attemptSuccessOutput(sprintf(__('Done processing %s items of %s', I18N::DOMAIN), $this->getProcessedItemCount(), $this->getImportEntityName()));
    }
}
