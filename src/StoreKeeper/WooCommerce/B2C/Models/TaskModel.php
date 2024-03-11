<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use Aura\SqlQuery\Common\SelectInterface;
use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class TaskModel extends AbstractModel implements IModelPurge
{
    public const TABLE_NAME = 'storekeeper_tasks';

    public static function getFieldsWithRequired(): array
    {
        return [
            'id' => true,
            'title' => true,
            'status' => false,
            'times_ran' => false,
            'name' => true,
            'type' => true,
            'type_group' => true,
            'storekeeper_id' => true,
            'execution_duration' => false,
            'date_last_processed' => false,
            'meta_data' => false,
            'error_output' => false,
            self::FIELD_DATE_CREATED => false,
            self::FIELD_DATE_UPDATED => false,
        ];
    }

    public static function newTask(
        string $title,
        string $type,
        string $type_group,
        int $storekeeper_id,
        array $meta_data = [],
        string $status = TaskHandler::STATUS_NEW,
        int $times_ran = 0,
        string $error_output = ''
    ): int {
        $data = compact(
            'title',
            'type',
            'type_group',
            'storekeeper_id',
            'status',
            'times_ran',
            'error_output',
            'meta_data'
        );
        $data['name'] = static::getName($type, $storekeeper_id);

        return static::create($data);
    }

    public static function getName(string $type, $storekeeper_id): string
    {
        if (intval($storekeeper_id) <= 0) {
            return $type;
        } else {
            return "$type::$storekeeper_id";
        }
    }

    public static function getLastProcessTaskDate(): ?\DateTime
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['date_last_processed'])
            ->orderBy(['date_last_processed DESC'])
            ->limit(1);

        $query = static::prepareQuery($select);

        $results = $wpdb->get_results($query, ARRAY_N);
        if (empty($results)) {
            return null;
        }

        $task = reset($results);

        return DatabaseConnection::formatFromDatabaseDateIfNotEmpty(reset($task));
    }

    public static function getTasksByStoreKeeperId(int $storeKeeperId, string $status = TaskHandler::STATUS_NEW): ?array
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['id'])
            ->where('storekeeper_id = :storekeeper_id')
            ->where('status = :status')
            ->bindValue('storekeeper_id', $storeKeeperId)
            ->bindValue('status', $status);

        $query = static::prepareQuery($select);

        $results = $wpdb->get_results($query, ARRAY_N);
        if (empty($results)) {
            return null;
        }

        return array_map(
            function ($value) {
                return (int) current($value);
            },
            $results
        );
    }

    public static function getLatestSuccessfulSynchronizedDateForType($type): ?\DateTime
    {
        global $wpdb;

        if (!$type) {
            throw new \RuntimeException('$type cannot be empty');
        }

        $select = static::getSelectHelper()
            ->cols(['date_last_processed'])
            ->where('status = :status')
            ->where('type = :type')
            ->bindValue('status', TaskHandler::STATUS_SUCCESS)
            ->bindValue('type', $type)
            ->orderBy(['date_last_processed DESC'])
            ->limit(1);

        $query = static::prepareQuery($select);

        $results = $wpdb->get_results($query, ARRAY_N);
        if (empty($results)) {
            return null;
        }

        $task = reset($results);

        return DatabaseConnection::formatFromDatabaseDateIfNotEmpty(reset($task));
    }

    public static function getFailedOrderIds(): array
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['meta_data'])
            ->where('status = :status')
            ->where('type = :type')
            ->bindValue('status', TaskHandler::STATUS_FAILED)
            ->bindValue('type', TaskHandler::ORDERS_EXPORT);

        $query = static::prepareQuery($select);

        $results = $wpdb->get_results($query, ARRAY_N);

        $orderIds = array_map(
            static function ($value) {
                $metadata = unserialize(current($value));
                $woocommerceId = $metadata['woocommerce_id'] ?? null;
                if ($woocommerceId) {
                    return (int) $woocommerceId;
                }

                return null;
            },
            $results
        );

        return array_values(
            array_unique(
                array_filter($orderIds)
            )
        );
    }

    public static function create(array $data): int
    {
        $data['meta_data'] = serialize($data['meta_data'] ?? []);

        return parent::create($data);
    }

    public static function read($id): ?array
    {
        $data = parent::read($id);
        if ($data) {
            $data['meta_data'] = unserialize($data['meta_data']);

            return $data;
        }

        return null;
    }

    public static function update($id, array $data): void
    {
        if (array_key_exists('meta_data', $data) && is_array($data['meta_data'])) {
            $data['meta_data'] = serialize($data['meta_data'] ?? []);
        }
        if (array_key_exists('error_output', $data) && is_string($data['error_output'])) {
            // strip any non-printable characters, otherwise it fails on wpdb->query
            // with "Could not perform query because it contains invalid data."
            $data['error_output'] = preg_replace('/[[:^print:]]/', '', $data['error_output']);
        }
        parent::update($id, $data);
    }

    public static function getMetaDataKey($id, string $key, $fallback = null)
    {
        $task = static::get($id);
        $metaData = $task['meta_data'];
        if (array_key_exists($key, $metaData)) {
            return $metaData[$key];
        }

        return $fallback;
    }

    public static function purge(): int
    {
        $affectedRows = 0;

        $affectedRows += static::purgeOrderThanXDays(30);

        if (static::count() > 1000) {
            $affectedRows += static::purgeOrderThanXDays(7);
            $affectedRows += static::purgeAllKeepLast1000();
        }

        return $affectedRows;
    }

    public static function countTasks(): int
    {
        return self::count(['status != :status'], ['status' => TaskHandler::STATUS_SUCCESS]);
    }

    public static function countFailedTasks(): int
    {
        return self::count(['status = :status'], ['status' => TaskHandler::STATUS_FAILED]);
    }

    public static function countSuccessfulTasks(): int
    {
        return self::count(['status = :status'], ['status' => TaskHandler::STATUS_SUCCESS]);
    }

    private static function prepareProcessedDateTimeRangeSelect(\DateTime $start, \DateTime $end): SelectInterface
    {
        return static::getSelectHelper()
            ->where('date_last_processed >= :start')
            ->where('date_last_processed <= :end')
            ->bindValue('start', DatabaseConnection::formatToDatabaseDate($start))
            ->bindValue('end', DatabaseConnection::formatToDatabaseDate($end));
    }

    private static function prepareCreatedDateTimeRangeSelect(\DateTime $start, \DateTime $end): SelectInterface
    {
        return static::getSelectHelper()
            ->where('date_created >= :start')
            ->where('date_created <= :end')
            ->bindValue('start', DatabaseConnection::formatToDatabaseDate($start))
            ->bindValue('end', DatabaseConnection::formatToDatabaseDate($end));
    }

    public static function getExecutionDurationSumByProcessedDateTimeRange(\DateTime $start, \DateTime $end): ?float
    {
        global $wpdb;

        $select = static::prepareProcessedDateTimeRangeSelect($start, $end);
        $select->cols(['SUM(execution_duration) AS duration_total']);

        $query = static::prepareQuery($select);

        return $wpdb->get_row($query)->duration_total;
    }

    public static function countTasksByCreatedDateTimeRange($start, $end): int
    {
        global $wpdb;

        $select = static::prepareCreatedDateTimeRangeSelect($start, $end);
        $select->cols(['COUNT(id) AS tasks_count']);

        $query = static::prepareQuery($select);

        $tasksCount = $wpdb->get_row($query)->tasks_count;

        return !is_null($tasksCount) ? $tasksCount : 0;
    }

    public static function countTasksByProcessedDateTimeRange(\DateTime $start, \DateTime $end): int
    {
        global $wpdb;

        $select = static::prepareProcessedDateTimeRangeSelect($start, $end);
        $select->cols(['COUNT(id) AS tasks_count']);

        $query = static::prepareQuery($select);

        $tasksCount = $wpdb->get_row($query)->tasks_count;

        return !is_null($tasksCount) ? $tasksCount : 0;
    }

    private static function getLastThousandSuccessfulTaskIds()
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['id'])
            ->where('status = :status')
            ->bindValue('status', TaskHandler::STATUS_SUCCESS)
            ->orderBy(['date_created DESC'])
            ->limit(1000);

        $query = static::prepareQuery($select);

        $results = $wpdb->get_results($query, ARRAY_N);

        return array_map(
            function ($value) {
                return (int) current($value);
            },
            $results
        );
    }

    private static function purgeAllKeepLast1000(): int
    {
        global $wpdb;

        $lastThousandSuccessfulIds = static::getLastThousandSuccessfulTaskIds();
        $exclude = "'".implode("','", $lastThousandSuccessfulIds)."'";

        $delete = static::getDeleteHelper()
            ->where('status = :status')
            ->where("id NOT IN ($exclude)")
            ->bindValue('status', TaskHandler::STATUS_SUCCESS);

        $affectedRows = $wpdb->query(static::prepareQuery($delete));

        AbstractModel::ensureAffectedRows($affectedRows);

        return (int) $affectedRows;
    }

    private static function purgeOrderThanXDays(int $numberOfDays): int
    {
        global $wpdb;

        $numberOfDays = absint($numberOfDays);

        $delete = static::getDeleteHelper()
            ->where('status = :status')
            ->where('date_created < :old')
            ->bindValue('status', TaskHandler::STATUS_SUCCESS)
            ->bindValue('old', DatabaseConnection::formatToDatabaseDate(
                (new \DateTime())->setTimestamp(strtotime("-$numberOfDays days"))
            ));

        $affectedRows = $wpdb->query(static::prepareQuery($delete));

        AbstractModel::ensureAffectedRows($affectedRows);

        return (int) $affectedRows;
    }
}
