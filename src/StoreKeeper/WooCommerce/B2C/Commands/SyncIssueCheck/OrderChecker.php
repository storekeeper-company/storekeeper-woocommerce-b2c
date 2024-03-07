<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\SyncIssueCheck;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use StoreKeeper\ApiWrapper\ApiWrapper;

class OrderChecker implements SyncIssueCheckerInterface
{
    use LoggerAwareTrait;

    /**
     * @var ApiWrapper
     */
    protected $api;

    protected $backend_paid_order_ids;

    protected $woocommerce_paid_orders;

    protected $woocommerce_order_post_ids_missing_backend_order_id;

    protected $report_text_output;

    protected $report_data;

    /**
     * OrderChecker constructor.
     */
    public function __construct(ApiWrapper $api)
    {
        $this->setLogger(new NullLogger());
        $this->api = $api;

        $this->backend_paid_order_ids = $this->fetchBackendPaidOrderIds();
        $this->woocommerce_paid_orders = $this->fetchWooCommercePaidOrders();
        $this->woocommerce_order_post_ids_missing_backend_order_id = $this->fetchWooCommercePostIdsMissingBackendOrderId(
        );
    }

    public function getReportTextOutput(): string
    {
        if (empty($this->report_text_output)) {
            $data = $this->getReportData();

            $orders_paid_in_woocommerce_not_paid_in_backend_post_ids = implode(
                ', ',
                $data['orders_paid_in_woocommerce_not_paid_in_backend']['post_ids']
            );
            $orders_paid_in_woocommerce_not_paid_in_backend_order_ids = implode(
                ', ',
                $data['orders_paid_in_woocommerce_not_paid_in_backend']['order_ids']
            );

            $woocommerce_order_post_ids_missing_backend_order_id_post_ids = implode(
                ', ',
                $data['woocommerce_order_post_ids_missing_backend_order_id']['post_ids']
            );

            $this->report_text_output = "
=== Orders paid in woocommerce but not paid in backend (From last 7 days) ===	
quantity: {$data['orders_paid_in_woocommerce_not_paid_in_backend']['amount']}
post_ids: $orders_paid_in_woocommerce_not_paid_in_backend_post_ids
order_ids: $orders_paid_in_woocommerce_not_paid_in_backend_order_ids

=== WooCommerce orders missing backend order id (storekeeper_id in meta data) (From last 7 days) ===	
quantity: {$data['woocommerce_order_post_ids_missing_backend_order_id']['amount']}
post_ids: $woocommerce_order_post_ids_missing_backend_order_id_post_ids
";
        }

        return $this->report_text_output;
    }

    public function getReportData(): array
    {
        if (empty($this->report_data)) {
            $this->logger->debug('Collected Data');

            $orders_paid_in_woocommerce_not_paid_in_backend = [];
            foreach ($this->woocommerce_paid_orders as $woocommerce_paid_order) {
                if (!in_array($woocommerce_paid_order['order_id'], $this->backend_paid_order_ids)) {
                    $orders_paid_in_woocommerce_not_paid_in_backend[] = $woocommerce_paid_order;
                }
            }

            $this->report_data = [
                'orders_paid_in_woocommerce_not_paid_in_backend' => [
                    'amount' => count($orders_paid_in_woocommerce_not_paid_in_backend),
                    'order_ids' => array_column($orders_paid_in_woocommerce_not_paid_in_backend, 'order_id'),
                    'post_ids' => array_column($orders_paid_in_woocommerce_not_paid_in_backend, 'post_id'),
                ],
                'woocommerce_order_post_ids_missing_backend_order_id' => [
                    'amount' => count($this->woocommerce_order_post_ids_missing_backend_order_id),
                    'post_ids' => $this->woocommerce_order_post_ids_missing_backend_order_id,
                ],
            ];
        }

        return $this->report_data;
    }

    public function isSuccess(): bool
    {
        $data = $this->getReportData();

        return 0 === $data['orders_paid_in_woocommerce_not_paid_in_backend']['amount']
            && 0 === $data['woocommerce_order_post_ids_missing_backend_order_id']['amount'];
    }

    private function fetchBackendPaidOrderIds()
    {
        $this->logger->debug('- Fetching Backend paid order ids');
        $ShopModule = $this->api->getModule('ShopModule');

        $backend_paid_order_ids = $ShopModule->listOrderIds(
            [
                [
                    'name' => 'is_paid__=',
                    'val' => true,
                ],
                [
                    'name' => 'date_created__>=',
                    'val' => $this->getDateOneWeekAgo(),
                ],
            ]
        );

        $this->logger->debug('- Done fetching Backend paid order ids');

        return $backend_paid_order_ids;
    }

    /**
     * @return array
     */
    private function fetchWooCommercePaidOrders()
    {
        $this->logger->debug('- Fetching WooCommerce paid order ids');

        global $wpdb;

        $date = $this->getDateOneWeekAgo();
        $sql = <<<SQL
SELECT posts.post_status as status, meta.meta_value as order_id, posts.ID as post_id
    FROM {$wpdb->prefix}posts as posts
        INNER JOIN {$wpdb->prefix}postmeta as meta
        ON posts.ID=meta.post_id
    WHERE posts.post_type = "shop_order"
        AND meta.meta_key="storekeeper_id"
        AND meta.meta_value IS NOT NULL
        AND posts.post_status = "wc-completed"
        AND posts.post_date >= "$date"
SQL;

        $woocommerce_paid_orders = $wpdb->get_results($sql, ARRAY_A);

        $this->logger->debug('- Done fetching WooCommerce paid order ids');

        return $woocommerce_paid_orders;
    }

    /**
     * @return array
     */
    private function fetchWooCommercePostIdsMissingBackendOrderId()
    {
        global $wpdb;

        $date = $this->getDateOneWeekAgo();
        $sql = <<<SQL
SELECT DISTINCT posts.ID as post_id
  FROM {$wpdb->prefix}posts as posts
    WHERE posts.post_type = "shop_order"
    AND posts.post_date >= "$date"
SQL;

        $woocommerce_orders = $wpdb->get_results($sql, ARRAY_A);
        $woocommerce_post_ids = array_column($woocommerce_orders, 'post_id');

        $woocommerce_order_post_ids_missing_backend_order_id = [];
        foreach ($woocommerce_post_ids as $woocommerce_post_id) {
            $storekeeper_id = get_post_meta($woocommerce_post_id, 'storekeeper_id');
            if (empty($storekeeper_id)) {
                $woocommerce_order_post_ids_missing_backend_order_id[] = $woocommerce_post_id;
            }
        }

        $this->logger->debug('- Done fetching WooCommerce paid order ids');

        return $woocommerce_order_post_ids_missing_backend_order_id;
    }

    /**
     * e.g. it is 2019-05-20 it will return 2019-05-13.
     */
    private function getDateOneWeekAgo(): string
    {
        $now = date('Y-m-d');

        return date('Y-m-d 00:00:00', strtotime("$now -7 days"));
    }
}
