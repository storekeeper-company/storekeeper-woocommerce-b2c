<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\Pages;

use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentPriceModel;
use function StoreKeeper\WooCommerce\B2C\__;
use function StoreKeeper\WooCommerce\B2C\esc_html;
use function StoreKeeper\WooCommerce\B2C\esc_sql;
use function StoreKeeper\WooCommerce\B2C\get_option;

class CustomerSegmentPricesTable extends \WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => __('Item', 'sp'),
            'plural' => __('Items', 'sp'),
            'ajax' => false,
        ]);
    }

    /**
     * @return array|object|\stdClass[]|null
     */
    public static function get_items($per_page = 5, $page_number = 1, $customer_segment_id)
    {
        global $wpdb;
        $name = CustomerSegmentPriceModel::getTableName();

        $sql = "SELECT * FROM {$name}";
        if (null !== $customer_segment_id) {
            $sql .= $wpdb->prepare(' WHERE customer_segment_id = %d', $customer_segment_id);
        }

        if (!empty($_REQUEST['orderby'])) {
            $sql .= ' ORDER BY '.esc_sql($_REQUEST['orderby']);
            $sql .= !empty($_REQUEST['order']) ? ' '.esc_sql($_REQUEST['order']) : ' ASC';
        }

        $sql .= " LIMIT $per_page";
        $sql .= ' OFFSET '.($page_number - 1) * $per_page;

        return $wpdb->get_results($sql, 'ARRAY_A');
    }

    /**
     * @return mixed
     */
    public static function record_count(): mixed
    {
        global $wpdb;
        $name = CustomerSegmentPriceModel::getTableName();

        $sql = "SELECT COUNT(*) FROM {$name}";

        return $wpdb->get_var($sql);
    }

    /**
     * @return void
     */
    public function no_items(): void
    {
        __('No items available.', I18N::DOMAIN);
    }

    /**
     * @param $item
     * @param $column_name
     * @return string
     */
    public function column_default($item, $column_name): string
    {
        switch ($column_name) {
            case 'id':
                return $item[$column_name];
            case 'customer_segment_id':
                $segment_name = $this->getCustomerSegmentName($item['customer_segment_id']);
                return $segment_name ? esc_html($segment_name) : __('N/A', I18N::DOMAIN);
            case 'product_id':
                $product_name = $this->getProductName($item['product_id']);
                return $product_name ? esc_html($product_name) : __('N/A', I18N::DOMAIN);
            case 'from_qty':
                return $item[$column_name];
            case 'ppu_wt':
                return $item[$column_name].' '.get_option('woocommerce_currency');
            default:
                return '';
        }
    }

    /**
     * @return array
     */
    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', I18N::DOMAIN),
            'customer_segment_id' => __('Customer Segment Name', I18N::DOMAIN),
            'product_id' => __('Product', I18N::DOMAIN),
            'from_qty' => __('From quantity', I18N::DOMAIN),
            'ppu_wt' => __('Price', I18N::DOMAIN),
        ];
    }

    /**
     * @return array[]
     */
    public function get_sortable_columns(): array
    {
        return [
            'id' => ['id', true],
            'customer_segment_id' => ['customer_segment_id', false],
            'product_id' => ['product_id', false],
            'from_qty' => ['from_qty', false],
            'ppu_wt' => ['ppu_wt', false],
        ];
    }

    /**
     * @return void
     */
    public function prepareItems(): void
    {
        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
        $per_page = $this->get_items_per_page('items_per_page', 5);
        $current_page = $this->get_pagenum();
        $total_items = self::record_count();
        $customer_segment_id = isset($_GET['id']) ? intval($_GET['id']) : null;

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        $this->items = self::get_items($per_page, $current_page, $customer_segment_id);
    }

    /**
     * @param $segment_id
     * @return mixed
     */
    public function getCustomerSegmentName($segment_id): mixed
    {
        global $wpdb;

        $segment = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT name FROM {$wpdb->prefix}storekeeper_customer_segments WHERE id = %d",
                $segment_id
            )
        );

        return $segment;
    }

    /**
     * @param $product_id
     * @return mixed
     */
    public function getProductName($product_id): mixed
    {
        global $wpdb;
        $product = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_title FROM {$wpdb->prefix}posts WHERE ID = %d AND post_type = 'product'  OR post_type = 'product_variation'",
                $product_id
            )
        );

        return $product;
    }
}
