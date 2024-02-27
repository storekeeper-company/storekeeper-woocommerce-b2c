<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use GuzzleHttp\Exception\ConnectException;
use StoreKeeper\WooCommerce\B2C\Exceptions\ProductImportException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Helpers\DateTimeHelper;
use StoreKeeper\WooCommerce\B2C\Options\StoreKeeperOptions;
use StoreKeeper\WooCommerce\B2C\Tools\WordpressExceptionThrower;
use WC_Product;

abstract class AbstractProductImport extends AbstractImport
{
    const STOCK_STATUS_IN_STOCK = 'instock';
    const STOCK_STATUS_OUT_OF_STOCK = 'outofstock';

    const SYNC_STATUS_PENDING = 'pending';
    const SYNC_STATUS_SUCCESS = 'success';
    const SYNC_STATUS_FAILED = 'failed';

    protected $woocommerceProductId = null;

    protected function getModule()
    {
        return 'ShopModule';
    }

    protected function getFunction()
    {
        return 'naturalSearchShopFlatProductForHooks';
    }

    protected function getQuery()
    {
        return 0;
    }

    protected function getFilters()
    {
        $f = [
            [
                'name' => 'flat_product/product/type__in_list',
                'multi_val' => [
                    'simple',
                    'configurable',
                    'configurable_assign',
                ],
            ],
        ];

        if ($this->storekeeper_id > 0) {
            $f[] = [
                'name' => 'id__=',
                'val' => $this->storekeeper_id,
            ];
        }

        if ($this->product_id > 0) {
            $f[] = [
                'name' => 'product_id__=',
                'val' => $this->product_id,
            ];
        }

        return $f;
    }

    protected $storekeeper_id = 0;
    protected $product_id = 0;

    /**
     * ProductImport constructor.
     *
     * @throws \Exception
     */
    public function __construct(array $settings = [])
    {
        $this->storekeeper_id = key_exists('storekeeper_id', $settings) ? (int) $settings['storekeeper_id'] : 0;
        $this->product_id = key_exists('product_id', $settings) ? (int) $settings['product_id'] : 0;
        unset($settings['storekeeper_id'], $settings['product_id']);
        parent::__construct($settings);
    }

    protected function setupLogData(Dot $dot): array
    {
        $log_data = [
            'id' => $dot->get('id'),
            'dirty' => $dot->get('flat_product.dirty'),
        ];
        if ($dot->has('flat_product.product.sku')) {
            $log_data['sku'] = $dot->get('flat_product.product.sku');
        }

        return $log_data;
    }

    /**
     * @param $sku
     *
     * @return WC_Product|bool
     *
     * @throws WordpressException
     */
    public static function getItemBySku($sku)
    {
        $products = WordpressExceptionThrower::throwExceptionOnWpError(
            get_posts(
                [
                    'post_type' => 'product',
                    'number' => 1,
                    'meta_key' => '_sku',
                    'meta_value' => $sku,
                    'suppress_filters' => false,
                    'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
                ]
            )
        );

        if (1 === count($products)) {
            return $products[0];
        }

        if (0 === count($products)) {
            return self::getItemByCustomSku($sku);
        }

        return false;
    }

    protected static function getItemByCustomSku($sku)
    {
        $skuWithDashes = trim((str_replace(' ', '-', $sku)));
        $products = WordpressExceptionThrower::throwExceptionOnWpError(
            get_posts(
                [
                    'post_type' => 'product',
                    'number' => 1,
                    'meta_key' => '_sku',
                    'meta_value' => $skuWithDashes,
                    'suppress_filters' => false,
                    'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
                ]
            )
        );

        if (1 === count($products)) {
            return $products[0];
        }

        if (0 === count($products)) {
            $skuWithUnderscores = trim((str_replace(' ', '_', $sku)));
            $products = WordpressExceptionThrower::throwExceptionOnWpError(
                get_posts(
                    [
                        'post_type' => 'product',
                        'number' => 1,
                        'meta_key' => '_sku',
                        'meta_value' => $skuWithUnderscores,
                        'suppress_filters' => false,
                        'post_status' => ['publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit'],
                    ]
                )
            );

            if (1 === count($products)) {
                return $products[0];
            }
        }

        return false;
    }

    public function setProductStock(WC_Product $product, Dot $dot, array $log_data): array
    {
        list($in_stock, $manage_stock, $stock_quantity) = $this->getStockProperties($dot);

        $product->set_manage_stock($manage_stock);
        $product->set_stock_quantity($stock_quantity);
        $product->set_stock_status(
            $in_stock
            ? self::STOCK_STATUS_IN_STOCK : self::STOCK_STATUS_OUT_OF_STOCK
        );
        $log_data['in_stock'] = $in_stock;
        $log_data['manage_stock'] = $product->get_manage_stock();
        $log_data['stock_quantity'] = $product->get_stock_quantity();
        $log_data['stock_status'] = $product->get_stock_status();
        $this->debug('Set stock on product', $log_data);

        return $log_data;
    }

    public function setProductBackorder(WC_Product $product, Dot $dot): void
    {
        $trueValue = $this->getBackorderTrueValue();
        $backorder_string = $dot->get('backorder_enabled', false) ? $trueValue : 'no';
        $product->set_backorders($backorder_string);
    }

    /**
     * @return string
     */
    protected function getBackorderTrueValue()
    {
        return 'yes' === StoreKeeperOptions::get(StoreKeeperOptions::NOTIFY_ON_BACKORDER, 'no') ? 'notify' : 'yes';
    }

    protected function getStockProperties(
        Dot $dot,
        string $shop_product_path = '',
        string $stock_path = 'flat_product.product.product_stock'
    ): array {
        $shop_product_path = rtrim($shop_product_path, '.');
        $stock_path = rtrim($stock_path, '.');
        if (!empty($shop_product_path) && !$dot->has($shop_product_path)) {
            throw new \Exception("No shop_product_path=$shop_product_path path found to get stock properties");
        }
        if (!empty($stock_path) && !$dot->has($stock_path)) {
            throw new \Exception("No stock_path=$stock_path path found to get stock properties");
        }
        $in_stock = $dot->get($this->cleanDotPath($stock_path.'.in_stock'));
        $stock_quantity = $dot->get($this->cleanDotPath($stock_path.'.value'), 9999);
        $unlimited_stock = $dot->get($this->cleanDotPath($stock_path.'.unlimited'));

        $orderable_stock_path = $this->cleanDotPath($shop_product_path.'.orderable_stock_value');

        if ($dot->has($orderable_stock_path) && !is_null($dot->get($orderable_stock_path))) {
            // Set stock based on orderable stock instead
            $orderable_stock_quantity = $dot->get($orderable_stock_path);
            $in_stock = $orderable_stock_quantity > 0;
            $stock_quantity = $orderable_stock_quantity;
        }

        if ($stock_quantity < 0) {
            $stock_quantity = 0;
        }

        if ($in_stock && 0 === $stock_quantity && !$unlimited_stock) {
            $in_stock = false;
        }

        $importProductType = $dot->get('flat_product.product.type');
        $manage_stock = true;
        if ($unlimited_stock || 'configurable' === $importProductType) {
            // Configurable product stock is not managed since variations are ordered on their own.
            // WooCommerce also seems to handle the stock status in WC_Product_Variable::validate_props()
            $manage_stock = false;
            $stock_quantity = null;
        }

        $dateUpdated = $dot->get($this->cleanDotPath($stock_path.'.date_updated'));

        return [$in_stock, $manage_stock, $stock_quantity, $dateUpdated];
    }

    protected function cleanDotPath(string $shop_product_path): string
    {
        return trim($shop_product_path, '.');
    }

    /**
     * @throws ProductImportException
     */
    protected function processItem($dotObject, array $options = []): ?int
    {
        $ShopModule = $this->storekeeper_api->getModule('ShopModule');

        $shopProductId = $dotObject->get('id');
        try {
            $woocommerceProductId = $this->doProcessProductItem($dotObject, $options);

            $this->logger->debug('Processed product', [
                'shop_product_id' => $shopProductId,
                'product_id' => $woocommerceProductId,
            ]);

            if (false !== $woocommerceProductId) {
                $ShopModule->setShopProductObjectSyncStatusForHook([
                    'status' => self::SYNC_STATUS_SUCCESS,
                    'shop_product_id' => $shopProductId,
                    'extra' => [
                        'product_id' => $woocommerceProductId,
                        'view_url' => get_permalink($woocommerceProductId),
                        'edit_url' => admin_url('post.php?post='.$woocommerceProductId).'&action=edit',
                        'date_synchronized' => DateTimeHelper::formatForStorekeeperApi(),
                        'plugin_version' => implode(', ', [
                            StoreKeeperOptions::PLATFORM_NAME.': '.get_bloginfo('version'),
                            StoreKeeperOptions::VENDOR.' plugin: '.STOREKEEPER_WOOCOMMERCE_B2C_VERSION,
                        ]),
                    ],
                ]);
            }

            return $shopProductId;
        } catch (ConnectException $exception) {
            // Throw the guzzle timeout exception as is
            throw $exception;
        } catch (\Throwable $throwable) {
            $data = [
                'status' => self::SYNC_STATUS_FAILED,
                'shop_product_id' => $shopProductId,
                'last_error_message' => $throwable->getMessage(),
                'last_error_details' => $throwable->getTraceAsString(),
            ];

            $productId = $this->getWoocommerceProductId();
            if (!is_null($productId)) {
                $data['extra'] = [
                    'product_id' => $productId,
                    'view_url' => get_permalink($productId),
                    'edit_url' => admin_url('post.php?post='.$productId).'&action=edit',
                    'plugin_version' => implode(', ', [
                        StoreKeeperOptions::PLATFORM_NAME.': '.get_bloginfo('version'),
                        StoreKeeperOptions::VENDOR.' plugin: '.STOREKEEPER_WOOCOMMERCE_B2C_VERSION,
                    ]),
                ];
            } else {
                $data['extra'] = [
                    'plugin_version' => implode(', ', [
                        StoreKeeperOptions::PLATFORM_NAME.': '.get_bloginfo('version'),
                        StoreKeeperOptions::VENDOR.' plugin: '.STOREKEEPER_WOOCOMMERCE_B2C_VERSION,
                    ]),
                ];
            }

            $ShopModule->setShopProductObjectSyncStatusForHook($data);

            throw new ProductImportException($shopProductId, $throwable->getMessage(), $throwable->getCode(), $throwable);
        }
    }

    public function getWoocommerceProductId(): ?int
    {
        return $this->woocommerceProductId;
    }

    public function setWoocommerceProductId(?int $woocommerceProductId): void
    {
        $this->woocommerceProductId = $woocommerceProductId;
    }

    abstract protected function doProcessProductItem($dotObject, array $options);
}
