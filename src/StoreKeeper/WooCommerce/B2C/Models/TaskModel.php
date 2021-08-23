<?php

namespace StoreKeeper\WooCommerce\B2C\Models;

use StoreKeeper\WooCommerce\B2C\Interfaces\IModelPurge;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class TaskModel extends AbstractModel implements IModelPurge
{
    const TABLE_NAME = 'storekeeper_tasks';

    public static function getFields(): array
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
            'meta_data' => false,
            'error_output' => false,
            'date_created' => false,
            'date_updated' => false,
        ];
    }

    public static function createTable(): bool
    {
        $name = self::getTableName();

        $tableQuery = <<<SQL
    CREATE TABLE `$name` (
        `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `status` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `times_ran` INT(10) DEFAULT 0,
        `name` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `type` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `type_group` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `storekeeper_id` INT(10) COLLATE utf8mb4_unicode_ci NOT NULL,
        `meta_data` LONGTEXT COLLATE utf8mb4_unicode_ci NOT NULL,
        `execution_duration` TEXT COLLATE utf8mb4_unicode_ci,
        `error_output` LONGTEXT COLLATE utf8mb4_unicode_ci NULL,
        `date_created` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
        `date_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP() NOT NULL,
        PRIMARY KEY (`id`),
        INDEX (type_group(100))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        return static::querySql($tableQuery);
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
        $data['meta_data'] = serialize($data['meta_data'] ?? []);
        $data = parent::updateDateField($data);
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
        return TaskModel::count(['status != :status'], ['status' => TaskHandler::STATUS_SUCCESS]);
    }

    public static function countFailedTasks(): int
    {
        return TaskModel::count(['status = :status'], ['status' => TaskHandler::STATUS_FAILED]);
    }

    public static function countSuccessfulTasks(): int
    {
        return TaskModel::count(['status = :status'], ['status' => TaskHandler::STATUS_SUCCESS]);
    }

    public static function getTasksByCreatedDateTimeRange($start, $end, $limit, $order = 'DESC')
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['id', 'date_created'])
            ->where('date_created >= :start')
            ->where('date_created <= :end')
            ->bindValue('start', $start)
            ->bindValue('end', $end)
            ->orderBy(['date_created '.$order])
            ->limit($limit);

        $query = static::prepareQuery($select);

        $results = $wpdb->get_results($query, ARRAY_N);

        return array_map(
            function ($value) {
                return [
                    'id' => (int) $value[0],
                    'date_created' => $value[1],
                ];
            },
            $results
        );
    }

    public static function getExecutionDurationSumByCreatedDateTimeRange($start, $end)
    {
        global $wpdb;

        $select = static::getSelectHelper()
            ->cols(['SUM(execution_duration) AS duration_total'])
            ->where('date_created >= :start')
            ->where('date_created <= :end')
            ->bindValue('start', $start)
            ->bindValue('end', $end);

        $query = static::prepareQuery($select);

        return $wpdb->get_row($query);
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
            ->bindValue('old', date('Y-m-d H:i:s', strtotime("-$numberOfDays days")));

        $affectedRows = $wpdb->query(static::prepareQuery($delete));

        AbstractModel::ensureAffectedRows($affectedRows);

        return (int) $affectedRows;
    }
}
