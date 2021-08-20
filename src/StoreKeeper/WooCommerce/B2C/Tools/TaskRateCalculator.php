<?php

namespace StoreKeeper\WooCommerce\B2C\Tools;

class TaskRateCalculator
{
    private $tasks;
    private $minuteMetrics;

    public function __construct(array $tasks, $minuteMetrics = 60)
    {
        $this->tasks = $tasks;
        $this->minuteMetrics = $minuteMetrics;
    }

    public function calculateIncoming($now = null): float
    {
        if (is_null($now)) {
            $now = date('Y-m-d H:i:s');
        }

        $oldestTaskDate = $this->getOldestTaskDate();

        $timeDifferenceInMinutes = abs(strtotime($now) - strtotime($oldestTaskDate)) / 60;
        $taskCount = count($this->tasks);
        $rate = $this->minuteMetrics * $taskCount / $timeDifferenceInMinutes;

        return round($rate, 1);
    }

    public function calculateProcessed()
    {
        $taskCount = count($this->tasks);

        $taskDuration = $this->getTaskDurationSum();

        $rate = $this->minuteMetrics * $taskCount / $taskDuration;

        return round($rate, 1);
    }

    private function getOldestTaskDate()
    {
        return min(array_map(function ($task) {
            return $task['date_created'];
        }, $this->tasks));
    }

    private function getTaskDurationSum()
    {
        $duration = array_sum(array_map(function ($task) {
            $metadata = unserialize($task['meta_data']);
            return $metadata['execution_duration_ms'] ?? 0;
        }, $this->tasks));
        return $duration > 0 ? $duration : 1;
    }
}
