<?php

namespace StoreKeeper\WooCommerce\B2C\Commands;

use StoreKeeper\WooCommerce\B2C\Database\DatabaseConnection;
use StoreKeeper\WooCommerce\B2C\Exceptions\BaseException;
use StoreKeeper\WooCommerce\B2C\Exceptions\ConnectionTimedOutException;
use StoreKeeper\WooCommerce\B2C\Exceptions\LockActiveException;
use StoreKeeper\WooCommerce\B2C\Exceptions\LockException;
use StoreKeeper\WooCommerce\B2C\Exceptions\SubProcessException;
use StoreKeeper\WooCommerce\B2C\I18N;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Query\CronQueryBuilder;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

/**
 * Processes all tasks on queue.
 */
class ProcessAllTasks extends AbstractCommand
{
    public const ARG_FAIL_ON_ERROR = 'fail-on-error';
    public const HAS_ERROR_TRANSIENT_KEY = 'process_has_error';
    public const MASSAGE_TASK_FAILED = 'Task failed';
    /**
     * @var \Throwable|null
     */
    private $lastError;
    /**
     * @var DatabaseConnection
     */
    protected $db;

    public static function getShortDescription(): string
    {
        return __('Process all queued synchronization tasks.', I18N::DOMAIN);
    }

    public static function getLongDescription(): string
    {
        return __('Process all queued synchronization tasks for products, categories, product stocks, tags, coupon codes, menu items, redirects and orders.', I18N::DOMAIN);
    }

    public static function getSynopsis(): array
    {
        return [
            [
                'type' => 'assoc',
                'name' => 'limit',
                'description' => __('Set how many tasks will be processed. Setting limit to 0 means all tasks will be processed.', I18N::DOMAIN),
                'optional' => true,
                'default' => 0,
            ],
            [
                'type' => 'flag',
                'name' => WpCliCommandRunner::SINGLE_PROCESS,
                'description' => __('Flag to prevent spawning of child processes. Having this might cause timeouts during execution.', I18N::DOMAIN),
                'optional' => true,
            ],
            [
                'type' => 'flag',
                'name' => self::ARG_FAIL_ON_ERROR,
                'description' => __('It will stop on first failing task', I18N::DOMAIN),
                'optional' => true,
            ],
        ];
    }

    /**
     * @throws \Exception
     */
    public function execute(array $arguments, array $assoc_arguments)
    {
        $rethrow = !empty($assoc_arguments[self::ARG_FAIL_ON_ERROR]);
        if ($rethrow) {
            $this->logger->notice('Processing will stop on first failing task');
        }
        $task_ids = [];
        $this->setUpDb();
        try {
            $this->lock();

            $limit = $this->getTaskLimitFromArguments($assoc_arguments);
            $task_ids = $this->getTaskIds($limit);
            $this->processTaskIds($task_ids, $rethrow);
        } catch (LockActiveException $exception) {
            $this->logger->notice('Cannot run. lock on.');
        } catch (\Throwable $exception) {
            $this->lastError = $exception;
            throw $exception;
        } finally {
            $this->setLastErrorTransient(count($task_ids));
            $this->deduplicateCron();
        }
    }

    private function getRemovedTaskIds($id): array
    {
        $task = $this->getTask($id);
        $metaData = unserialize($task['meta_data']);

        if (array_key_exists('removed_task_ids', $metaData)) {
            return $metaData['removed_task_ids'];
        }

        return [];
    }

    private function ensureLastRunTime()
    {
        if ($this->hasLastRunTime()) {
            $this->db->querySql(CronQueryBuilder::getUpdateLastRunTimeSql());
        } else {
            $this->db->querySql(CronQueryBuilder::getInsertLastRunTimeSql());
        }
    }

    private function hasLastRunTime()
    {
        $row = $this->db->getRow(CronQueryBuilder::getCountLastRunTimeSql());

        return !empty($row) && count($row) > 0 && $row[0] > 0;
    }

    private function ensureSuccessRunTime()
    {
        if ($this->hasSuccessRunTime()) {
            $this->db->querySql(CronQueryBuilder::getUpdateSuccessRunTimeSql());
        } else {
            $this->db->querySql(CronQueryBuilder::getInsertSuccessRunTimeSql());
        }
    }

    private function hasSuccessRunTime()
    {
        $row = $this->db->getArray(CronQueryBuilder::getCountSuccessRunTimeSql());

        return !empty($row) && array_key_exists('count', $row) && $row['count'] > 0;
    }

    private function getCronOption()
    {
        $data = $this->db->getArray(CronQueryBuilder::getCronOptionSql());

        if ($data && array_key_exists('option_value', $data)) {
            $unserialized = unserialize($data['option_value']);
        } else {
            $unserialized = [];
        }

        return $unserialized;
    }

    private function setCronOption(array $data): void
    {
        $sql = CronQueryBuilder::updateCronOptionSql($this->db, $data);
        $this->db->querySql($sql);
    }

    private function deduplicateCron(): void
    {
        $cron = $this->getCronOption();
        if (empty($cron)) {
            return;
        }

        $newCron = [
            'version' => $cron['version'],
        ];

        $deDuplicateTasks = ['delete_version_transients', 'woocommerce_flush_rewrite_rules'];
        $deDuplicateCrons = [];

        // Loop over current crons
        foreach ($cron as $index => $data) {
            if (is_array($data)) {
                $keys = array_keys($data);

                // Check if it is one of the banned crons, is so. de duplicate it.
                if (0 === count(array_intersect($keys, $deDuplicateTasks))) {
                    $newCron[$index] = $data;
                } else {
                    $key = $keys[0];
                    $deDuplicateCrons[$key] = [
                        'index' => $index,
                        'data' => $data,
                    ];
                }
            }
        }

        // Loop over all de duplicated crons and add the back.
        foreach ($deDuplicateCrons as $cronData) {
            $newCron[$cronData['index']] = $cronData['data'];
        }

        $this->setCronOption($newCron);
    }

    /**
     * @throws \Exception
     */
    protected function reportTaskError($task_id, \Throwable $e, array $log_context): void
    {
        $this->lastError = $e;

        $task = $this->getTask($task_id);
        $this->updateTaskStatus($task, TaskHandler::STATUS_FAILED);

        $error_parts = [
            'plugin_version' => constant('STOREKEEPER_WOOCOMMERCE_B2C_VERSION'),
            'task_url' => $this->getSiteUrl()."/wp-admin/admin.php?page=storekeeper-logs&task-id=$task_id",
            'exception' => BaseException::getAsString($e),
        ];

        if ($e instanceof SubProcessException) {
            $process = $e->getProcess();
            $error_parts['process_output'] = $process->getOutput();
            $error_parts['process_error'] = $process->getErrorOutput();
            $error_parts['process_exit_code'] = $process->getExitCode();
        }

        $error = '';
        foreach ($error_parts as $name => $error_part) {
            $error .= "\n===[ $name ]===\n$error_part\n";
        }

        $task = $this->getTask($task_id);
        $task['error_output'] = $error;
        $this->updateTask($task['id'], $task);

        $this->logger->error(self::MASSAGE_TASK_FAILED, $log_context + $error_parts);
    }

    protected function getSiteUrl(): string
    {
        // Check if we can access the get_site_url wordpress function, if not, use empty string
        $has_wordpress = function_exists('get_site_url');
        $site_url = $has_wordpress ? get_site_url() : '';

        return (string) $site_url;
    }

    public static function getOrderTaskIds(): array
    {
        $db = new DatabaseConnection();

        $select = TaskModel::getSelectHelper()
            ->cols(['id'])
            ->where('type IN (:type1,:type2,:type3,:type4)')
            ->where('status = :status')
            ->bindValue('status', TaskHandler::STATUS_NEW)
            ->bindValue('type1', TaskHandler::ORDERS_IMPORT)
            ->bindValue('type2', TaskHandler::ORDERS_EXPORT)
            ->bindValue('type3', TaskHandler::ORDERS_DELETE)
            ->bindValue('type4', TaskHandler::ORDERS_PAYMENT_STATUS_CHANGE)
            ->limit(100);

        $query = $db->prepare($select);
        $results = $db->querySql($query)->fetch_all();

        return array_map('current', $results);
    }

    public static function getNonOrderTaskIds(): array
    {
        $db = new DatabaseConnection();

        $select = TaskModel::getSelectHelper()
            ->cols(['id'])
            ->where('type NOT IN (:type1,:type2,:type3,:type4)')
            ->where('status = :status')
            ->bindValue('status', TaskHandler::STATUS_NEW)
            ->bindValue('type1', TaskHandler::ORDERS_IMPORT)
            ->bindValue('type2', TaskHandler::ORDERS_EXPORT)
            ->bindValue('type3', TaskHandler::ORDERS_DELETE)
            ->bindValue('type4', TaskHandler::ORDERS_PAYMENT_STATUS_CHANGE)
            ->orderBy(['times_ran ASC', 'id ASC'])
            ->limit(100);

        $query = $db->prepare($select);
        $results = $db->getResults($query);

        return array_map('current', $results);
    }

    protected function updateTaskStatus(array $task, string $status): void
    {
        $task['status'] = $status;
        $this->updateTask($task['id'], $task);
    }

    protected function getTaskIds(int $limit = 0): array
    {
        $this->ensureLastRunTime();

        $order_task_ids = self::getOrderTaskIds();
        $this->logger->info(
            'Found order tasks',
            [
                'total' => count($order_task_ids),
            ]
        );

        $this->logger->debug(
            'Order task ids',
            [
                'task_ids' => $order_task_ids,
            ]
        );

        $non_order_task_ids = self::getNonOrderTaskIds();
        $this->logger->info(
            'Found non-order tasks',
            [
                'total' => count($non_order_task_ids),
            ]
        );
        $this->logger->debug(
            'non-order task ids',
            [
                'post_ids' => $non_order_task_ids,
            ]
        );

        $task_ids = array_merge($order_task_ids, $non_order_task_ids);

        if (0 !== $limit) {
            $task_ids = array_slice($task_ids, 0, $limit);
        }

        return $task_ids;
    }

    public static function countNewTasks(): int
    {
        $db = new DatabaseConnection();

        $select = TaskModel::getSelectHelper()
            ->cols(['COUNT(id) AS tasks_count'])
            ->where('status = :status')
            ->bindValue('status', TaskHandler::STATUS_NEW);

        $query = $db->prepare($select);

        $row = $db->getRow($query);

        return !is_null($row[0]) ? $row[0] : 0;
    }

    private function getTask($id)
    {
        $select = TaskModel::getSelectHelper()
            ->cols(['*'])
            ->where('id = :id')
            ->bindValue('id', $id);

        $query = $this->db->prepare($select);

        return $this->db->getArray($query);
    }

    private function updateTask($id, $data)
    {
        TaskModel::update($id, $data);
    }

    protected function setLastErrorTransient(int $task_quantity): void
    {
        if (0 !== $task_quantity) {
            set_transient(
                self::HAS_ERROR_TRANSIENT_KEY,
                is_null($this->lastError) ? 'no' : 'yes',
                300
            ); // 5 minutes transient expiry
        }
    }

    protected function setUpDb(): void
    {
        $this->db = new DatabaseConnection();
        $this->logger->debug(
            'Connected to DB',
            [
                'host' => DB_HOST,
                'user' => DB_USER,
                'db' => DB_NAME,
            ]
        );
    }

    protected function getTaskLimitFromArguments(array $assoc_arguments): int
    {
        $limit = 0;
        if (isset($assoc_arguments['limit'])) {
            $limit = (int) $assoc_arguments['limit'];
            $this->logger->notice(
                'Limiting process',
                [
                    'process_limit_count' => $limit,
                ]
            );
        }

        return $limit;
    }

    /**
     * @throws \Throwable
     * @throws ConnectionTimedOutException
     * @throws BaseException
     * @throws LockException
     */
    protected function processTaskIds(array $task_ids, bool $rethrow): void
    {
        $task_quantity = count($task_ids);

        $this->logger->info(
            'Tasks to process',
            [
                'total' => $task_quantity,
            ]
        );

        $removed_task_ids = [];
        foreach ($task_ids as $index => $task_id) {
            if (in_array($task_id, $removed_task_ids)) {
                continue; // task is removed
            }

            $task = $this->getTask($task_id);

            if (!$task) {
                $this->logger->notice(
                    'Task not found -> skipping',
                    [
                        'post_id' => $task_id,
                    ]
                );
                continue;
            }

            if (!in_array($task['status'], [TaskHandler::STATUS_NEW, TaskHandler::STATUS_SUCCESS], true)) {
                $this->logger->notice(
                    'Task not in new state -> skipping',
                    [
                        'post_id' => $task_id,
                        'state' => $task['status'],
                    ]
                );
                continue;
            }

            $log_context = [
                'index' => $index,
                'task_id' => $task_id,
                'task_left' => $task_quantity - $index - 1,
            ];
            try {
                $this->updateTaskStatus($task, TaskHandler::STATUS_PROCESSING);
                $this->executeSubCommand(ProcessSingleTask::getCommandName(), [$task_id]);

                // Check if running the tasks remove any of the old tasks
                $removed_task_ids = array_merge(
                    $removed_task_ids,
                    $this->getRemovedTaskIds($task['id'])
                );

                $this->reportTaskSuccess($task_id, $log_context);
            } catch (LockException $e) {
                // Do not report the task error
                throw $e;
            } catch (ConnectionTimedOutException $exception) {
                // Catch ConnectionTimedOutException class from API curl
                // Do not report the task error
                throw $exception;
            } catch (SubProcessException $exception) {
                // Catch ConnectionTimedOutException class from spawned subprocess
                // This catch block is when processing all tasks via scheduled processor
                $process = $exception->getProcess();
                if (false === strpos($process->getErrorOutput(), ConnectionTimedOutException::class)) {
                    // Report error and set task status to failed if not ConnectionTimedOutException
                    // Otherwise, ignore the error as it was already rescheduled when it happened the first time
                    $this->reportTaskError($task_id, $exception, $log_context);

                    if ($rethrow) {
                        throw $exception;
                    }
                }
            } catch (\Throwable $e) {
                $this->reportTaskError($task_id, $e, $log_context);

                if ($rethrow) {
                    throw $e;
                }
            }
        }
    }

    protected function reportTaskSuccess($task_id, array $log_context)
    {
        $task = $this->getTask($task_id);
        $this->updateTaskStatus($task, TaskHandler::STATUS_SUCCESS);
        $this->ensureSuccessRunTime();

        $this->logger->info('Task success', $log_context);
    }
}
