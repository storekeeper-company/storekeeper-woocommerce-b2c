<?php

namespace StoreKeeper\WooCommerce\B2C\Commands\SyncIssueCheck;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use StoreKeeper\WooCommerce\B2C\Models\TaskModel;
use StoreKeeper\WooCommerce\B2C\Tools\TaskHandler;

class TaskChecker implements SyncIssueCheckerInterface
{
    use LoggerAwareTrait;

    /**
     * @var array
     */
    private $failed_tasks = [];

    /**
     * cache.
     */
    private $formattedFailedTasks = [];

    public function __construct()
    {
        $this->setLogger(new NullLogger());
        $this->failed_tasks = $this->fetchFailedTasks();
    }

    public function isSuccess(): bool
    {
        return 0 === count($this->failed_tasks); //return success
    }

    public function getReportTextOutput(): string
    {
        // collecting the data;
        $failedTasks = $this->getFailedTasks();

        // Formatting failed tasks
        list($failedTasks_amount, $failedTasks_postIds, $failedTasks_links) = $this->formatFailedTasks($failedTasks);
        $failedTasks_note = '-';
        if ($failedTasks_amount >= 100) {
            $failedTasks_note = 'There are a hundred failed tasks. which is the max amount of failed tasks we fetch. So there might be more.';
        }

        return "
=== Failed tasks ===	
Note: $failedTasks_note
quantity: $failedTasks_amount
post_ids: $failedTasks_postIds
task_links: 
$failedTasks_links";
    }

    public function getReportData(): array
    {
        $failedTasks = $this->getFailedTasks();

        $this->logger->debug('Collected Data');

        return [
            'failed_tasks' => [
                'amount' => $failedTasks['amount'],
                'post_ids' => $failedTasks['post_ids'],
            ],
        ];
    }

    /**
     * @return array|object|null
     */
    private function fetchFailedTasks()
    {
        global $wpdb;

        $select = TaskModel::getSelectHelper()
            ->cols(
                [
                    'id as ID',
                    'status as task_status',
                ]
            )
            ->where('status = :status')
            ->bindValue('status', TaskHandler::STATUS_FAILED)
            ->limit(100);

        $this->logger->debug('- Fetching failed Tasks');

        return $wpdb->get_results(TaskModel::prepareQuery($select));
    }

    /**
     * @return array
     */
    private function getFailedTasks()
    {
        if (!$this->formattedFailedTasks) {
            $amount = 0;
            $post_ids = [];

            foreach ($this->failed_tasks as $failed_task) {
                ++$amount;
                $post_ids[] = $failed_task->ID;
            }

            $this->formattedFailedTasks = [
                'amount' => $amount,
                'post_ids' => $post_ids,
            ];
        }

        return $this->formattedFailedTasks;
    }

    /**
     * @param $failedTasks
     *
     * @return array
     */
    private function formatFailedTasks($failedTasks)
    {
        $failedTasks_amount = (string) $failedTasks['amount'];
        $failedTasks_postIds = implode(', ', $failedTasks['post_ids']);
        $failedTasks_links = '';
        foreach ($failedTasks['post_ids'] as $post_id) {
            $failedTasks_links .= get_site_url(null, "/wp-admin/post.php?post=$post_id&action=edit").PHP_EOL;
        }

        return [$failedTasks_amount, $failedTasks_postIds, $failedTasks_links];
    }
}
