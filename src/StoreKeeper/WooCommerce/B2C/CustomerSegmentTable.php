<?php

namespace StoreKeeper\WooCommerce\B2C;

use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentModel;
use StoreKeeper\WooCommerce\B2C\Models\CustomerSegmentPriceModel;
use StoreKeeper\WooCommerce\B2C\Models\CustomersSegmentsModel;

class CustomerSegmentTable extends \WP_List_Table
{
    public function __construct()
    {
        parent::__construct([
            'singular' => __('Item', 'sp'),
            'plural' => __('Items', 'sp'),
            'ajax' => false,
        ]);
    }

    public function showImportForm(): void
    {
        ?>
        <div class="alignleft actions">
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('import_csv_action', 'import_csv_nonce'); ?>
                <button type="submit" name="action" value="import_customer_segments" class="button button-primary">
                    <?php
                        echo __('Import Customer Segment Prices', I18N::DOMAIN);
        ?>
                </button>
                <?php if (isset($_POST['action']) && 'import_customer_segments' === $_POST['action']) { ?>
                    <input type="file" name="csv_file" accept=".csv" required style="width:50%;">
                <?php } ?>
            </form>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('import_csv_action', 'import_csv_nonce'); ?>
                <button type="submit" name="action" value="import_segment_to_customer" class="button button-primary" style="margin-top: 10px;">
                    <?php
            echo __('Import Customer Segments', I18N::DOMAIN);
        ?>
                </button>
                <?php if (isset($_POST['action']) && 'import_segment_to_customer' === $_POST['action']) { ?>
                    <input type="file" name="csv_file" accept=".csv" required style="width:50%; margin-top: 10px;">
                <?php } ?>
            </form>
        </div>
        <?php
    }

    /**
     * @return array|object|\stdClass[]|null
     */
    public static function getItems($perPage = 5, $pageNumber = 1)
    {
        global $wpdb;
        $name = CustomerSegmentModel::getTableName();
        $sql = "SELECT * FROM {$name}";

        if (!empty($_REQUEST['orderby'])) {
            $sql .= ' ORDER BY '.esc_sql($_REQUEST['orderby']);
            $sql .= !empty($_REQUEST['order']) ? ' '.esc_sql($_REQUEST['order']) : ' ASC';
        }

        $sql .= " LIMIT $perPage";
        $sql .= ' OFFSET '.($pageNumber - 1) * $perPage;

        return $wpdb->get_results($sql, 'ARRAY_A');
    }

    public static function recordCount(): mixed
    {
        global $wpdb;
        $name = CustomerSegmentModel::getTableName();

        $sql = "SELECT COUNT(*) FROM {$name}";

        return $wpdb->get_var($sql);
    }

    public function no_items(): void
    {
        __('No items available.', I18N::DOMAIN);
    }

    public function column_default($item, $column_name): string
    {
        switch ($column_name) {
            case 'id':
            case 'customer_email':
            case 'name':
                return $item[$column_name];
            case 'action':
                return '<a href="'.esc_url(admin_url('admin.php?page=customer_segment&action=view&id='.$item['id'])).
                    '" class="button button-outline-primary">'.__('Show', I18N::DOMAIN).'</a>';
            default:
                return '';
        }
    }

    public function get_columns(): array
    {
        return [
            'cb' => '<input type="checkbox" />',
            'id' => __('ID', I18N::DOMAIN),
            'customer_email' => __('Customer Email', I18N::DOMAIN),
            'name' => __('Name', I18N::DOMAIN),
            'action' => __('Action', I18N::DOMAIN),
        ];
    }

    /**
     * @return array[]
     */
    public function getSortableColumns(): array
    {
        return [
            'id' => ['id', true],
            'customer_email' => ['customer_email', false],
            'name' => ['name', false],
            'action' => ['action', false],
        ];
    }

    public function prepareItems(): void
    {
        if ('POST' === $_SERVER['REQUEST_METHOD']
            && isset($_POST['import_csv_nonce'])
            && wp_verify_nonce($_POST['import_csv_nonce'], 'import_csv_action')
            && isset($_FILES['csv_file'])
            && !empty($_FILES['csv_file']['tmp_name'])
        ) {
            $this->handleImportAction();
        }

        $this->_column_headers = [$this->get_columns(), [], $this->getSortableColumns()];
        $per_page = $this->get_items_per_page('items_per_page', 5);
        $current_page = $this->get_pagenum();
        $total_items = self::recordCount();

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        $this->items = self::getItems($per_page, $current_page);
    }

    public function handleImportAction(): void
    {
        if (isset($_POST['import_csv_nonce']) && wp_verify_nonce($_POST['import_csv_nonce'], 'import_csv_action')) {
            $action = isset($_POST['action']) ? sanitize_text_field($_POST['action']) : '';

            if (!empty($_FILES['csv_file']['tmp_name'])) {
                $csv_file = $_FILES['csv_file']['tmp_name'];

                if ('import_customer_segments' == $action) {
                    $this->importCustomerSegmentsWithPrices($csv_file);
                } elseif ('import_segment_to_customer' == $action) {
                    $this->importSegmentCustomers($csv_file);
                } else {
                }
            } else {
                echo __('Please upload a CSV file.', I18N::DOMAIN);
            }
        }
    }

    private function importCustomerSegmentsWithPrices($csv_file): void
    {
        global $wpdb;

        $segmentPricesTable = CustomerSegmentPriceModel::getTableName();
        $customerSegmentsTable = CustomerSegmentModel::getTableName();

        if (($handle = fopen($csv_file, 'r')) !== false) {
            fgetcsv($handle, 1000, ';');
            $row = 1;

            while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                if (1 === count($data)) {
                    $data = explode(';', $data[0]);
                }

                if (count($data) < 4) {
                    ++$row;
                    continue;
                }

                $customerSegmentName = sanitize_text_field($data[0]);
                $productSku = sanitize_text_field($data[1]);
                $fromQty = sanitize_text_field($data[2]);
                $ppu_wt = sanitize_text_field($data[3]);
                $customerSegmentId = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM $customerSegmentsTable WHERE name = %s LIMIT 1",
                        $customerSegmentName
                    )
                );

                if ($customerSegmentId) {
                    $productId = $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT post_id FROM {$wpdb->prefix}postmeta WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1",
                            $productSku
                        )
                    );

                    $wpdb->insert(
                        $segmentPricesTable,
                        [
                            'customer_segment_id' => $customerSegmentId,
                            'product_id' => $productId,
                            'from_qty' => $fromQty,
                            'ppu_wt' => $ppu_wt,
                        ],
                        [
                            '%d',
                            '%s',
                            '%s',
                            '%s',
                        ]
                    );
                } else {
                    echo '<div class="notice notice-error"><p>'.sprintf(__('Row %s is missing expected columns', I18N::DOMAIN), $row + 1).'</p></div>';
                }

                ++$row;
            }

            fclose($handle);

            echo '<div class="notice notice-success"><p>'.__('CSV imported successfully!', I18N::DOMAIN).'</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>'.__('Failed to open the file.', I18N::DOMAIN).'</p></div>';
        }
    }

    private function importSegmentCustomers($csvFile): void
    {
        global $wpdb;

        $customersInSegmentsTable = CustomersSegmentsModel::getTableName();
        $customerSegmentsTable = CustomerSegmentModel::getTableName();

        if (($handle = fopen($csvFile, 'r')) !== false) {
            fgetcsv($handle, 1000, ';');
            $dataToInsert = [];
            $row = 1;
            while (($data = fgetcsv($handle, 1000, ';')) !== false) {
                if (1 == count($data)) {
                    $data = explode(';', $data[0]);
                }

                if (isset($data[0]) && isset($data[1])) {
                    $customerEmail = sanitize_text_field($data[0]);
                    $name = sanitize_text_field($data[1]);
                    $dataToInsert[] = [$customerEmail, $name];
                } else {
                    echo '<div class="notice notice-error"><p>'.sprintf(__('Row %s is missing expected columns', I18N::DOMAIN), $row + 1).'</p></div>';
                }

                ++$row;
            }

            if (!empty($dataToInsert)) {
                $placeholders = array_fill(0, count($dataToInsert), '(%s, %s)');
                $sql = "INSERT INTO $customerSegmentsTable (customer_email, name) VALUES ".implode(', ', $placeholders);
                $flattenedData = [];

                foreach ($dataToInsert as $rowData) {
                    $flattenedData = array_merge($flattenedData, $rowData);
                }

                $wpdb->query($wpdb->prepare($sql, ...$flattenedData));

                foreach ($dataToInsert as $rowData) {
                    $customerEmail = $rowData[0];
                    $customerSegmentIds = $wpdb->get_col(
                        $wpdb->prepare(
                            "SELECT id FROM $customerSegmentsTable WHERE customer_email = %s",
                            $customerEmail
                        )
                    );
                    $customerId = $wpdb->get_var(
                        $wpdb->prepare("SELECT ID FROM {$wpdb->users} WHERE user_email = %s", $customerEmail)
                    );

                    if (!empty($customerSegmentIds)) {
                        foreach ($customerSegmentIds as $customerSegmentId) {
                            $existingEntry = $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT COUNT(*) FROM $customersInSegmentsTable WHERE customer_id = %d AND customer_segment_id = %d",
                                    $customerId, $customerSegmentId
                                )
                            );

                            if (0 == $existingEntry) {
                                $wpdb->insert(
                                    $customersInSegmentsTable,
                                    [
                                        'customer_id' => $customerId,
                                        'customer_segment_id' => $customerSegmentId,
                                    ],
                                    [
                                        '%d',
                                        '%d',
                                    ]
                                );
                            } else {
                                echo sprintf(__('Skipping duplicate segment with ID: %s', I18N::DOMAIN), $customerSegmentId).'<br>';
                            }
                        }
                    } else {
                        echo sprintf(__('No segments found for customer: %s', I18N::DOMAIN), $customerEmail).'<br>';
                    }
                }

                echo '<div class="notice notice-success"><p>'.__('CSV imported successfully!', I18N::DOMAIN).'</p></div>';
            }

            fclose($handle);
        } else {
            echo '<div class="notice notice-error"><p>'.__('Failed to open the file.', I18N::DOMAIN).'</p></div>';
        }
    }
}
