<?php

namespace StoreKeeper\WooCommerce\B2C\Imports;

use Adbar\Dot;
use Exception;
use StoreKeeper\ApiWrapper\Exception\GeneralException;
use StoreKeeper\WooCommerce\B2C\Exceptions\NonExistentObjectException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Tools\StoreKeeperApi;
use WC_Order;

class OrderImport extends AbstractImport
{
    public const ORDER_PAGE_META_KEY = 'storekeeper_order_page_url';
    private $storekeeper_id;
    private $new_order;
    private $old_order;

    /**
     * OrderImport constructor.
     *
     * @throws Exception
     */
    public function __construct(array $settings = [])
    {
        $this->storekeeper_id = key_exists('storekeeper_id', $settings) ? (int) $settings['storekeeper_id'] : [];
        $this->new_order = key_exists('new', $settings) ? $settings['new'] : [];
        $this->old_order = key_exists('old', $settings) ? $settings['old'] : [];

        parent::__construct();
    }

    /**
     * @param array $options
     *
     * @return array|bool
     *
     * @throws Exception
     */
    public function run($options = [])
    {
        try {
            $this->processItem(new Dot($this->new_order));
        } catch (NonExistentObjectException $exception) {
            $this->logger->info('Order import is marked as success', [
                'storekeeper_id' => $this->storekeeper_id,
                'message' => $exception->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * @param Dot $dotObject
     *
     * @throws Exception
     * @throws NonExistentObjectException
     */
    protected function processItem($dotObject, array $options = [])
    {
        global $storekeeper_ignore_order_id;
        $order = $this->getItem($this->storekeeper_id);

        if (false === $order) {
            throw new NonExistentObjectException('Tried to import an non-existing order: '.$this->storekeeper_id);
        }

        if (true === $order) {
            throw new Exception('Fount more orders with id: '.$this->storekeeper_id);
        }

        /** Set the ignore order id to prevent an update loop (wc > backend > wc > etc) */
        $storekeeper_ignore_order_id = $order->get_id();

        $is_paid = (bool) $dotObject->get('is_paid');
        $wc_status = $order->get_status('edit');
        $sk_status = self::getWoocommerceStatus($dotObject->get('status'));

        self::ensureOrderStatusUrl($order, $this->storekeeper_id);

        /*
         * We first check if we need to apply the storekeeper wc status to the order
         */
        if ($sk_status && $this->canUpdateStatus($wc_status, $sk_status)) {
            $wc_status = $sk_status; // We are about to update the wc order status
            $order->set_status($sk_status);
            $order->save();
        }

        /*
         * We need to ensure the wc_status is not pending if the order is marked as paid.
         * So if the order is paid, we mark the order as processing.
         */
        if ($is_paid && 'pending' === $wc_status) {
            $order->set_status('processing');
            $order->save();
        }
    }

    /**
     * @param $storekeeper_id
     *
     * @return bool|WC_Order
     */
    private function getItem($storekeeper_id)
    {
        global $wpdb;

        $sql = <<<SQL
SELECT ID FROM {$wpdb->prefix}posts
INNER JOIN {$wpdb->prefix}postmeta
ON {$wpdb->prefix}posts.ID={$wpdb->prefix}postmeta.post_id
WHERE {$wpdb->prefix}postmeta.meta_key='storekeeper_id'
AND {$wpdb->prefix}postmeta.meta_value=%d
AND {$wpdb->prefix}posts.post_type='shop_order'
SQL;

        $safe_sql = $wpdb->prepare($sql, $storekeeper_id);

        $response = $wpdb->get_row($safe_sql);

        if (isset($response->ID)) {
            return new WC_Order($response->ID);
        }

        return false;
    }

    public static function getWoocommerceStatus($storekeeper_status)
    {
        switch ($storekeeper_status) {
            case 'new':
                return null;
                break;
            case 'cancelled':
                return 'cancelled';
                break;
            case 'complete':
                return 'completed';
                break;
            case 'refunded':
                return 'refunded';
                break;
            case 'on_hold':
                return 'on-hold';
                break;
            case 'processing':
                return 'processing';
                break;
            default:
                return $storekeeper_status;
        }
    }

    /**
     * @param $current_status
     * @param $new_status
     *
     * @return bool
     */
    private function canUpdateStatus($current_status, $new_status)
    {
        $upgrade_tree = [
            'pending' => [
                'cancelled',
                'completed',
                'refunded',
                'on-hold',
                'processing',
            ],
            'cancelled' => [
                'refunded',
            ],
            'completed' => [],
            'refunded' => [],
            'on-hold' => [
                'cancelled',
                'completed',
                'refunded',
                'pending',
                'processing',
            ],
            'processing' => [
                'cancelled',
                'completed',
                'refunded',
                'pending',
                'on-hold',
            ],
        ];

        if (!key_exists($current_status, $upgrade_tree)) {
            return false;
        }

        $upgrade_branch = $upgrade_tree[$current_status];

        return in_array($new_status, $upgrade_branch);
    }

    protected function getImportEntityName(): string
    {
        return __('orders', I18N::DOMAIN);
    }

    public static function ensureOrderStatusUrl(WC_Order $order, int $storekeeperId): ?string
    {
        $apiWrapper = StoreKeeperApi::getApiByAuthName();
        $shopModule = $apiWrapper->getModule('ShopModule');
        $storekeeperOrder = $shopModule->getOrder($storekeeperId, null);
        $orderStatusUrl = $order->get_meta(self::ORDER_PAGE_META_KEY, true);

        if (isset($storekeeperOrder['shipped_item_no']) && empty($orderStatusUrl)) {
            $shippedItem = (int) $storekeeperOrder['shipped_item_no'];
            if ($shippedItem > 0) {
                $orderStatusUrl = self::fetchOrderStatusUrl($order, $storekeeperId);
            }
        }

        return $orderStatusUrl;
    }

    /**
     * @throws Exception
     */
    public static function fetchOrderStatusUrl(WC_Order $order, int $storekeeperId): ?string
    {
        $apiWrapper = StoreKeeperApi::getApiByAuthName();
        $shopModule = $apiWrapper->getModule('ShopModule');
        $orderStatusPageUrl = null;
        try {
            $orderStatusPageUrl = $shopModule->getOrderStatusPageUrl($storekeeperId);
            $order->update_meta_data(self::ORDER_PAGE_META_KEY, $orderStatusPageUrl);
            $order->save();
        } catch (GeneralException $generalException) {
            LoggerFactory::create('order')->error($generalException->getMessage(), ['trace' => $generalException->getTraceAsString()]);
        }

        return $orderStatusPageUrl;
    }
}
