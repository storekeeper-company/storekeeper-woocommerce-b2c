<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

use Exception;
use GuzzleHttp\Exception\ConnectException;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use StoreKeeper\WooCommerce\B2C\Exceptions\ConnectionTimedOutException;
use StoreKeeper\WooCommerce\B2C\Exceptions\LockException;
use StoreKeeper\WooCommerce\B2C\Exceptions\WordpressException;
use StoreKeeper\WooCommerce\B2C\Factories\LoggerFactory;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tasks\AttributeImportTask;
use StoreKeeper\WooCommerce\B2C\Tasks\CategoryDeleteTask;
use StoreKeeper\WooCommerce\B2C\Tasks\CategoryImportTask;
use StoreKeeper\WooCommerce\B2C\Tasks\CouponCodeDeleteTask;
use StoreKeeper\WooCommerce\B2C\Tasks\CouponCodeImportTask;
use StoreKeeper\WooCommerce\B2C\Tasks\MenuItemDeleteTask;
use StoreKeeper\WooCommerce\B2C\Tasks\MenuItemImportTask;
use StoreKeeper\WooCommerce\B2C\Tasks\OrderDeleteTask;
use StoreKeeper\WooCommerce\B2C\Tasks\OrderExportTask;
use StoreKeeper\WooCommerce\B2C\Tasks\OrderImportTask;
use StoreKeeper\WooCommerce\B2C\Tasks\OrderPaymentStatusUpdateTask;
use StoreKeeper\WooCommerce\B2C\Tasks\ParentProductRecalculationTask;
use StoreKeeper\WooCommerce\B2C\Tasks\ProductActivateTask;
use StoreKeeper\WooCommerce\B2C\Tasks\ProductDeactivateTask;
use StoreKeeper\WooCommerce\B2C\Tasks\ProductDeleteTask;
use StoreKeeper\WooCommerce\B2C\Tasks\ProductImportTask;
use StoreKeeper\WooCommerce\B2C\Tasks\ProductStockUpdateTask;
use StoreKeeper\WooCommerce\B2C\Tasks\ProductUpdateImportTask;
use StoreKeeper\WooCommerce\B2C\Tasks\RedirectDeleteTask;
use StoreKeeper\WooCommerce\B2C\Tasks\RedirectImportTask;
use StoreKeeper\WooCommerce\B2C\Tasks\ShippingMethodDeleteTask;
use StoreKeeper\WooCommerce\B2C\Tasks\ShippingMethodImportTask;
use StoreKeeper\WooCommerce\B2C\Tasks\TagDeleteTask;
use StoreKeeper\WooCommerce\B2C\Tasks\TagImportTask;
use StoreKeeper\WooCommerce\B2C\Tasks\TriggerVariationSaveActionTask;

class TaskHandler
{
    use LoggerAwareTrait;

    /**
     * NOTE: When adding an constant here related to a new task/import type make sure you also add it to
     * const TASKS_BY_PRIORITY_ASC in this file to prioritize it.
     */
    public const FULL_IMPORT = 'full-import';
    public const ATTRIBUTE_IMPORT = 'attribute-import';
    public const CATEGORY_IMPORT = 'category-import';
    public const PRODUCT_IMPORT = 'product-import';
    public const PRODUCT_UPDATE = 'product-update';
    public const PRODUCT_STOCK_UPDATE = 'product-stock-update';
    public const TAG_IMPORT = 'tag-import';
    public const COUPON_CODE_IMPORT = 'coupon-code-import';

    public const MENU_ITEM_IMPORT = 'menu-item-import';
    public const MENU_ITEM_DELETE = 'menu-item-delete';

    public const REDIRECT_IMPORT = 'redirect-import';
    public const REDIRECT_DELETE = 'redirect-delete';

    public const CATEGORY_DELETE = 'category-delete';
    public const PRODUCT_DELETE = 'product-delete';
    public const PRODUCT_DEACTIVATED = 'product-deactivated';
    public const PRODUCT_ACTIVATED = 'product-activated';

    public const TAG_DELETE = 'tag-delete';
    public const COUPON_CODE_DELETE = 'coupon-code-delete';

    public const ORDERS_EXPORT = 'orders-export';
    public const ORDERS_IMPORT = 'orders-import';
    public const ORDERS_PAYMENT_STATUS_CHANGE = 'orders-payment-status-change';
    public const ORDERS_DELETE = 'orders-delete';
    public const SHIPPING_METHOD_IMPORT = 'shipping-method-import';
    public const SHIPPING_METHOD_DELETE = 'shipping-method-delete';

    public const STATUS_NEW = 'status-new';
    public const STATUS_PROCESSING = 'status-processing';
    public const STATUS_FAILED = 'status-failed';
    public const STATUS_SUCCESS = 'status-success';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_PROCESSING,
        self::STATUS_FAILED,
        self::STATUS_SUCCESS,
    ];

    public const TRIGGER_VARIATION_SAVE_ACTION = 'trigger-variation-save-action';
    public const PARENT_PRODUCT_RECALCULATION = 'parent-product-recalculation';

    public const REPORT_ERROR = 'report-error';

    public const OTHER_TYPE_GROUP = 'other';
    public const PRODUCT_TYPE_GROUP = 'product';
    public const CATEGORY_TYPE_GROUP = 'category';
    public const PRODUCT_STOCK_TYPE_GROUP = 'product-stock';
    public const TAG_TYPE_GROUP = 'tag';
    public const COUPON_CODE_TYPE_GROUP = 'coupon-code';
    public const MENU_ITEM_TYPE_GROUP = 'menu-item';
    public const REDIRECT_TYPE_GROUP = 'redirect';
    public const ORDER_TYPE_GROUP = 'order';
    public const TRIGGER_VARIATION_SAVE_ACTION_TYPE_GROUP = 'trigger-variation-save-action';
    public const PARENT_PRODUCT_RECALCULATION_TYPE_GROUP = 'parent-product-recalculation';
    public const SHIPPING_METHOD_GROUP = 'shipping-method';
    public const REPORT_ERROR_TYPE_GROUP = 'report-error';

    public const TYPE_GROUPS = [
        self::OTHER_TYPE_GROUP,
        self::PRODUCT_TYPE_GROUP,
        self::CATEGORY_TYPE_GROUP,
        self::PRODUCT_STOCK_TYPE_GROUP,
        self::TAG_TYPE_GROUP,
        self::COUPON_CODE_TYPE_GROUP,
        self::MENU_ITEM_TYPE_GROUP,
        self::REDIRECT_TYPE_GROUP,
        self::ORDER_TYPE_GROUP,
        self::TRIGGER_VARIATION_SAVE_ACTION_TYPE_GROUP,
        self::PARENT_PRODUCT_RECALCULATION_TYPE_GROUP,
        self::SHIPPING_METHOD_GROUP,
        self::REPORT_ERROR_TYPE_GROUP,
    ];

    protected $trashed_tasks = [];

    private static $typeRegex = '/(.*)::(.*)/';

    public static function getStatusLabel(string $status): string
    {
        switch ($status) {
            case TaskHandler::STATUS_NEW:
                return __('New', I18N::DOMAIN);
            case TaskHandler::STATUS_PROCESSING:
                return __('Processing', I18N::DOMAIN);
            case TaskHandler::STATUS_SUCCESS:
                return __('Success', I18N::DOMAIN);
            case TaskHandler::STATUS_FAILED:
                return __('Failed', I18N::DOMAIN);
            default:
                return $status;
        }
    }

    /**
     * @param int $id
     */
    public static function getScheduledTask($type, $id = 0)
    {
        global $wpdb;

        $select = TaskModel::getSelectHelper()
            ->cols(['id'])
            ->where('name = :name')
            ->where('status != :status')
            ->bindValue('name', TaskModel::getName($type, $id))
            ->bindValue('status', TaskHandler::STATUS_SUCCESS)
            ->limit(1);

        $id = $wpdb->get_var(TaskModel::prepareQuery($select));

        return TaskModel::get($id);
    }

    public function __construct()
    {
        $this->setLogger(new NullLogger());
    }

    public function getTrashedTasks()
    {
        return $this->trashed_tasks;
    }

    public function addTrashedTask($task_id)
    {
        $this->trashed_tasks[] = $task_id;
    }

    private static function getTypeGroup($type): string
    {
        switch ($type) {
            case self::CATEGORY_IMPORT:
            case self::CATEGORY_DELETE:
                return self::CATEGORY_TYPE_GROUP;
            case self::PRODUCT_IMPORT:
            case self::PRODUCT_UPDATE:
            case self::PRODUCT_DELETE:
            case self::PRODUCT_DEACTIVATED:
            case self::PRODUCT_ACTIVATED:
                return self::PRODUCT_TYPE_GROUP;
            case self::PRODUCT_STOCK_UPDATE:
                return self::PRODUCT_STOCK_TYPE_GROUP;
            case self::TAG_IMPORT:
            case self::TAG_DELETE:
                return self::TAG_TYPE_GROUP;
            case self::COUPON_CODE_IMPORT:
            case self::COUPON_CODE_DELETE:
                return self::COUPON_CODE_TYPE_GROUP;
            case self::ORDERS_EXPORT:
            case self::ORDERS_IMPORT:
            case self::ORDERS_DELETE:
            case self::ORDERS_PAYMENT_STATUS_CHANGE:
                return self::ORDER_TYPE_GROUP;
            case self::TRIGGER_VARIATION_SAVE_ACTION:
                return self::TRIGGER_VARIATION_SAVE_ACTION_TYPE_GROUP;
            case self::PARENT_PRODUCT_RECALCULATION:
                return self::PARENT_PRODUCT_RECALCULATION_TYPE_GROUP;
            case self::MENU_ITEM_IMPORT:
            case self::MENU_ITEM_DELETE:
                return self::MENU_ITEM_TYPE_GROUP;
            case self::REDIRECT_IMPORT:
            case self::REDIRECT_DELETE:
                return self::REDIRECT_TYPE_GROUP;
            case self::SHIPPING_METHOD_IMPORT:
            case self::SHIPPING_METHOD_DELETE:
                return self::SHIPPING_METHOD_GROUP;
            case self::REPORT_ERROR:
                return self::REPORT_ERROR_TYPE_GROUP;
            default:
                return self::OTHER_TYPE_GROUP;
        }
    }

    public static function getTypeGroupTitle($typeId): string
    {
        switch ($typeId) {
            case self::PRODUCT_TYPE_GROUP:
                return __('Product', I18N::DOMAIN);
            case self::CATEGORY_TYPE_GROUP:
                return __('Category', I18N::DOMAIN);
            case self::PRODUCT_STOCK_TYPE_GROUP:
                return __('Product stock', I18N::DOMAIN);
            case self::TAG_TYPE_GROUP:
                return __('Tag', I18N::DOMAIN);
            case self::COUPON_CODE_TYPE_GROUP:
                return __('Coupon code', I18N::DOMAIN);
            case self::MENU_ITEM_TYPE_GROUP:
                return __('Menu item', I18N::DOMAIN);
            case self::REDIRECT_TYPE_GROUP:
                return __('Redirect', I18N::DOMAIN);
            case self::ORDER_TYPE_GROUP:
                return __('Order', I18N::DOMAIN);
            case self::TRIGGER_VARIATION_SAVE_ACTION_TYPE_GROUP:
                return __('Trigger variation save actions', I18N::DOMAIN);
            case self::PARENT_PRODUCT_RECALCULATION_TYPE_GROUP:
                return __('Parent product recalculation', I18N::DOMAIN);
            case self::SHIPPING_METHOD_GROUP:
                return __('Shipping method', I18N::DOMAIN);
            case self::REPORT_ERROR_TYPE_GROUP:
                return __('Report error', I18N::DOMAIN);
            default:
                return __('Other', I18N::DOMAIN);
        }
    }

    private static function getTitle($type, $id = 0)
    {
        switch ($type) {
            case self::FULL_IMPORT:
                $title = __('Full import', I18N::DOMAIN);
                break;
            case self::ATTRIBUTE_IMPORT:
                $title = __('Attribute import', I18N::DOMAIN)."(id=$id)";
                break;
            case self::CATEGORY_IMPORT:
                $title = __('Category import', I18N::DOMAIN)."(id=$id)";
                break;
            case self::PRODUCT_IMPORT:
                $title = __('Product import', I18N::DOMAIN)."(id=$id)";
                break;
            case self::PRODUCT_UPDATE:
                $title = __('Product update', I18N::DOMAIN)."(id=$id)";
                break;
            case self::PRODUCT_STOCK_UPDATE:
                $title = __('Product stock update', I18N::DOMAIN)."(id=$id)";
                break;
            case self::TAG_IMPORT:
                $title = __('Tag import', I18N::DOMAIN)."(id=$id)";
                break;
            case self::COUPON_CODE_IMPORT:
                $title = __('Coupon code import', I18N::DOMAIN)."(id=$id)";
                break;
            case self::ORDERS_EXPORT:
                $title = __('Order export', I18N::DOMAIN)."(id=$id)";
                break;
            case self::ORDERS_IMPORT:
                $title = __('Order import', I18N::DOMAIN)."(id=$id)";
                break;
            case self::ORDERS_PAYMENT_STATUS_CHANGE:
                $title = __('Order payment status change')."(id=$id)";
                break;
            case self::CATEGORY_DELETE:
                $title = __('Delete category', I18N::DOMAIN)."(id=$id)";
                break;
            case self::PRODUCT_DELETE:
                $title = __('Delete product', I18N::DOMAIN)."(id=$id)";
                break;
            case self::PRODUCT_DEACTIVATED:
                $title = __('Deactiveer product', I18N::DOMAIN)."(id=$id)";
                break;
            case self::PRODUCT_ACTIVATED:
                $title = __('Activeer product', I18N::DOMAIN)."(id=$id)";
                break;
            case self::TAG_DELETE:
                $title = __('Delete tag', I18N::DOMAIN)."(id=$id)";
                break;
            case self::ORDERS_DELETE:
                $title = __('Delete order', I18N::DOMAIN)."(id=$id)";
                break;
            case self::COUPON_CODE_DELETE:
                $title = __('Delete coupon code', I18N::DOMAIN)."(id=$id)";
                break;
            case self::TRIGGER_VARIATION_SAVE_ACTION:
                $title = __('Trigger variation save actions', I18N::DOMAIN)."(id=$id)";
                break;
            case self::PARENT_PRODUCT_RECALCULATION:
                $title = __('Parent product recalculation', I18N::DOMAIN)."(id=$id)";
                break;
            case self::MENU_ITEM_IMPORT:
                $title = __('Menu item import', I18N::DOMAIN)."(id=$id)";
                break;
            case self::MENU_ITEM_DELETE:
                $title = __('Menu item delete', I18N::DOMAIN)."(id=$id)";
                break;
            case self::REDIRECT_IMPORT:
                $title = __('Redirect import', I18N::DOMAIN)."(id=$id)";
                break;
            case self::REDIRECT_DELETE:
                $title = __('Redirect delete', I18N::DOMAIN)."(id=$id)";
                break;
            case self::SHIPPING_METHOD_IMPORT:
                if ($id > 0) {
                    $title = __('Shipping method import', I18N::DOMAIN)."(id=$id)";
                } else {
                    $title = __('All shipping method import', I18N::DOMAIN);
                }
                break;
            case self::SHIPPING_METHOD_DELETE:
                $title = __('Shipping method delete', I18N::DOMAIN)."(id=$id)";
                break;
            case self::REPORT_ERROR:
                $title = __('Report error', I18N::DOMAIN)."(task_id=$id)";
                break;
            default:
                $title = "$type (id=$id)";
                break;
        }

        return $title;
    }

    public function trashTasks(string $type, string $id): void
    {
        global $wpdb;

        $delete = TaskModel::getDeleteHelper()
            ->where('name = :name')
            ->bindValue('name', TaskModel::getName($type, $id));

        $wpdb->query(TaskModel::prepareQuery($delete));
    }

    public function rescheduleTask($type, $id = 0, $metadata = [])
    {
        $this->trashTasks($type, $id);

        return self::scheduleTask($type, $id, $metadata);
    }

    /**
     * @param int    $storekeeper_id
     * @param array  $meta_data
     * @param bool   $force_add
     * @param string $task_status
     */
    public static function scheduleTask(
        $type,
        $storekeeper_id = 0,
        $meta_data = [],
        $force_add = false,
        $task_status = self::STATUS_NEW
    ) {
        $existingTask = self::getScheduledTask($type, $storekeeper_id);
        if (!$force_add && !empty($existingTask)) {
            // Dont update status if the status is new of success
            if (!in_array($existingTask['status'], [self::STATUS_NEW, self::STATUS_SUCCESS])) {
                $existingTask['status'] = self::STATUS_NEW;
                TaskModel::update($existingTask['id'], $existingTask);
            }

            return $existingTask;
        }

        TaskModel::newTask(
            self::getTitle($type, $storekeeper_id),
            $type,
            self::getTypeGroup($type),
            (int) $storekeeper_id,
            $meta_data,
            $task_status
        );

        return self::getScheduledTask($type, $storekeeper_id);
    }

    /**
     * @param array $task_options
     *
     * @return array|bool
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function handleTask(int $task_id, string $typeName, $task_options = [])
    {
        $regexOutput = preg_match(self::$typeRegex, $typeName, $regexMatches);

        $this->logger->debug('Handle import', [
            'typeName' => $typeName,
            'task_id' => $task_id,
            'task_options' => $task_options,
        ]);

        if (0 === $regexOutput) {
            $type = $typeName;
            $storekeeper_id = 0;
        } else {
            if (1 === $regexOutput && count($regexMatches) > 0) {
                $type = $regexMatches[1];
                $storekeeper_id = $regexMatches[2];
                $storekeeper_id = absint($storekeeper_id);
            } else {
                return false;
            }
        }

        $this->logger->debug('Got the task type: '.$type);
        if (self::FULL_IMPORT === $type) {
            return true;
        }

        switch ($type) {
            case self::ATTRIBUTE_IMPORT:
                $import = new AttributeImportTask();
                break;
            case self::CATEGORY_IMPORT:
                $import = new CategoryImportTask();
                break;
            case self::PRODUCT_IMPORT:
                $import = new ProductImportTask();
                break;
            case self::PRODUCT_UPDATE:
                $import = new ProductUpdateImportTask();
                break;
            case self::PRODUCT_STOCK_UPDATE:
                $import = new ProductStockUpdateTask();
                break;
            case self::TAG_IMPORT:
                $import = new TagImportTask();
                break;
            case self::ORDERS_EXPORT:
                $import = new OrderExportTask();
                break;
            case self::ORDERS_IMPORT:
                $import = new OrderImportTask();
                break;
            case self::ORDERS_PAYMENT_STATUS_CHANGE:
                $import = new OrderPaymentStatusUpdateTask();
                break;
            case self::CATEGORY_DELETE:
                $import = new CategoryDeleteTask();
                break;
            case self::PRODUCT_DELETE:
                $import = new ProductDeleteTask();
                break;
            case self::PRODUCT_ACTIVATED:
                $import = new ProductActivateTask();
                break;
            case self::PRODUCT_DEACTIVATED:
                $import = new ProductDeactivateTask();
                break;
            case self::TAG_DELETE:
                $import = new TagDeleteTask();
                break;
            case self::ORDERS_DELETE:
                $import = new OrderDeleteTask();
                break;
            case self::COUPON_CODE_IMPORT:
                $import = new CouponCodeImportTask();
                break;
            case self::COUPON_CODE_DELETE:
                $import = new CouponCodeDeleteTask();
                break;
            case self::TRIGGER_VARIATION_SAVE_ACTION:
                $import = new TriggerVariationSaveActionTask();
                break;
            case self::PARENT_PRODUCT_RECALCULATION:
                $import = new ParentProductRecalculationTask();
                break;
            case self::MENU_ITEM_IMPORT:
                $import = new MenuItemImportTask();
                break;
            case self::MENU_ITEM_DELETE:
                $import = new MenuItemDeleteTask();
                break;
            case self::REDIRECT_IMPORT:
                $import = new RedirectImportTask();
                break;
            case self::REDIRECT_DELETE:
                $import = new RedirectDeleteTask();
                break;
            case self::SHIPPING_METHOD_IMPORT:
                $import = new ShippingMethodImportTask();
                break;
            case self::SHIPPING_METHOD_DELETE:
                $import = new ShippingMethodDeleteTask();
                break;
            case self::REPORT_ERROR:
                return; // nothing to handle
            default:
                throw new \Exception("$type not found");
        }

        $import->setLogger($this->logger);
        $import->setTaskHandler($this);

        $this->logger->debug(
            'Got the task',
            [
                'class' => get_class($import),
            ]
        );

        if ($storekeeper_id > 0) {
            $import->setId($storekeeper_id);
        }

        $import->setTaskId($task_id);

        // Load all task meta for the task
        $taskMeta = $import->loadTaskMeta();

        $this->logger->debug(
            'Got the task meta',
            [
                'meta' => $taskMeta,
            ]
        );

        $this->logger->info(
            'Running the task',
            [
                'type' => $type,
                'options' => $task_options,
                'class' => get_class($import),
            ]
        );

        try {
            $import->increaseTimesRan();
            $import->run($task_options);

            $this->logger->notice(
                'Task success',
                [
                    'type' => $type,
                    'options' => $task_options,
                    'class' => get_class($import),
                ]
            );

            // Recount categories because WP cant
            _wc_term_recount(
                get_terms(
                    'product_cat',
                    ['hide_empty' => false, 'fields' => 'id=>parent']
                ),
                get_taxonomy('product_cat'),
                true,
                false
            );

            // Recount Labels because WP cant
            _wc_term_recount(
                get_terms('product_tag', ['hide_empty' => false, 'fields' => 'id=>parent']),
                get_taxonomy('product_tag'),
                true,
                false
            );
        } catch (LockException $e) {
            throw $e; // do not report the task eror
        } catch (ConnectException $exception) {
            // Reschedule the task on timeout for the first time
            // Report as error otherwise
            $isRescheduled = self::rescheduleTaskOnTimeout($task_id);
            $this->logger->warning("Connection timed out during API call (id=$task_id)");

            if ($isRescheduled) {
                throw new ConnectionTimedOutException($task_id, ConnectionTimedOutException::class, $exception->getCode(), $exception);
            }

            $this->reportFailedTask($task_id, $exception);
            throw $exception;
        } catch (\Throwable $e) {
            // If the task failed, report it
            $this->reportFailedTask($task_id, $e);
            $this->logger->error("Failed to run the task (id=$task_id)");

            // Throw the exception so it can be picked up by process-single-task
            throw $e;
        }
    }

    public static function rescheduleTaskOnTimeout(int $task_id): bool
    {
        if (0 !== $task_id) {
            $existingTask = TaskModel::get($task_id);

            if ($existingTask && $existingTask['times_ran'] <= 1) {
                if (self::STATUS_NEW === $existingTask['status']) {
                    return true;
                }

                $existingTask['status'] = self::STATUS_NEW;
                TaskModel::update($task_id, $existingTask);

                return true;
            }
        }

        return false;
    }

    /**
     * Reports a failed task.
     *
     * @param int        $task_id   Task id of the task that failed
     * @param \Exception $exception The exception that occurred
     *
     * @throws WordpressException
     */
    private function reportFailedTask($task_id, $exception)
    {
        $metaData = [];

        if (0 !== $task_id) {
            // If task id is not 0, it must be a failed task that is being reported
            $metaData['failed-task-task-id'] = $task_id;
            $metaData['failed-task-task-link'] = get_site_url(
                null,
                "/wp-admin/admin.php?page=storekeeper-logs&task-id=$task_id"
            );

            /*
             * failed-task-try is the try that the task was ran on.
             * So if this is the 4th time this task runs, it will
             * be reported as the 4th try.
             */
            $data = TaskModel::get($task_id);
            if ($data) {
                $metaData['failed-task-try'] = $data['times_ran'];
                $custom_metadata = $data['meta_data'] ?? [];
                if (is_string($custom_metadata)) {
                    $custom_metadata = unserialize($custom_metadata);
                }
                $generatedMetadata = LoggerFactory::generateMetadata('task-in-queue-failed', $exception, $custom_metadata);
                $data['meta_data'] = $generatedMetadata;
                TaskModel::update($task_id, $data);
            }
        }

        // Create an error task in the task queue
        LoggerFactory::createErrorTask('task-in-queue-failed', $exception, $metaData);
    }

    /**
     * Get all new tasks by storekeeper id.
     */
    public static function getNewTasksByStorekeeperId(int $storekeeperId): ?array
    {
        global $wpdb;

        $select = TaskModel::getSelectHelper()
            ->cols(['*'])
            ->where('status = :status')
            ->where('storekeeper_id = :storekeeper_id')
            ->bindValue('status', self::STATUS_NEW)
            ->bindValue('storekeeper_id', $storekeeperId)
            ->orderBy(['date_created DESC']);

        return $wpdb->get_results(TaskModel::prepareQuery($select), ARRAY_A);
    }
}
